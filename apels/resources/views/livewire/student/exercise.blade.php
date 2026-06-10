<?php

use App\Models\Module;
use App\Models\Question;
use App\Models\UserModuleProgress;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Exercise/Quiz per Modul (Req 15.1-15.5).
 *
 * Load questions matching module tag, score on submit,
 * mark module completed if score >= 70.
 */
new #[Layout('components.layouts.app')] class extends Component {
    public ?Module $module = null;
    public array $questions = [];
    public array $answers = [];
    public ?int $score = null;
    public bool $submitted = false;
    public bool $passed = false;

    public function mount(int $module): void
    {
        $this->module = Module::findOrFail($module);

        // Guard: check unlocked
        $progress = UserModuleProgress::where('user_id', auth()->id())
            ->where('module_id', $this->module->id)
            ->first();

        if (!$progress || !$progress->is_unlocked) {
            session()->flash('error', 'Modul ini masih terkunci.');
            $this->redirect(route('learning-path'), navigate: true);
            return;
        }

        $this->loadQuestions();
    }

    private function loadQuestions(): void
    {
        // Req 15.1: questions matching module tag, active only
        $questions = Question::active()
            ->byTag($this->module->tag)
            ->inRandomOrder()
            ->limit(10)
            ->get();

        $this->questions = $questions->map(fn ($q) => [
            'id'        => $q->id,
            'question'  => $q->question,
            'option_a'  => $q->option_a,
            'option_b'  => $q->option_b,
            'option_c'  => $q->option_c,
            'option_d'  => $q->option_d,
            'correct'   => $q->correct_answer,
        ])->toArray();

        // Initialize answers
        $this->answers = array_fill(0, count($this->questions), null);
    }

    /**
     * Submit answers and calculate score (Req 15.3-15.5).
     */
    public function submit(): void
    {
        if (empty($this->questions)) {
            return;
        }

        $correct = 0;
        $total = count($this->questions);

        foreach ($this->questions as $index => $q) {
            if (isset($this->answers[$index]) && $this->answers[$index] === $q['correct']) {
                $correct++;
            }
        }

        $this->score = $total > 0 ? (int) round($correct / $total * 100) : 0;
        $this->submitted = true;
        $this->passed = $this->score >= 70;

        // Update module progress
        $userId = auth()->id();

        if ($this->passed) {
            // Req 15.4: mark completed
            UserModuleProgress::updateOrCreate(
                ['user_id' => $userId, 'module_id' => $this->module->id],
                [
                    'is_completed' => true,
                    'score'        => $this->score,
                    'completed_at' => now(),
                ]
            );
        } else {
            // Req 15.5: save score only, allow retry
            UserModuleProgress::updateOrCreate(
                ['user_id' => $userId, 'module_id' => $this->module->id],
                ['score' => $this->score]
            );
        }
    }

    /**
     * Reset for retry.
     */
    public function retry(): void
    {
        $this->submitted = false;
        $this->score = null;
        $this->passed = false;
        $this->answers = array_fill(0, count($this->questions), null);
        $this->loadQuestions();
    }
}; ?>

<div class="max-w-3xl mx-auto py-8 px-4">
    <div class="mb-6">
        <a href="{{ route('module-detail', $module->id) }}" wire:navigate class="text-blue-600 text-sm hover:underline">← Kembali ke Modul</a>
    </div>

    <h1 class="text-2xl font-bold mb-2">Latihan: {{ $module->title }}</h1>

    @if (empty($questions))
        <div class="text-center py-12 text-gray-500">
            <p>Belum ada soal untuk modul ini. Hubungi dosen Anda.</p>
        </div>
    @elseif ($submitted)
        {{-- Result --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-2">Hasil Latihan</h2>
            <div class="text-4xl font-bold mb-2 {{ $passed ? 'text-green-600' : 'text-red-600' }}">
                {{ $score }}%
            </div>
            <p class="text-gray-600">
                @if ($passed)
                    🎉 Selamat! Kamu lulus latihan ini. Modul ditandai selesai.
                @else
                    Skor minimal 70% untuk menyelesaikan modul. Silakan coba lagi.
                @endif
            </p>
            <div class="mt-4 flex gap-3">
                @if (!$passed)
                    <flux:button wire:click="retry" variant="primary">Coba Lagi</flux:button>
                @endif
                <a href="{{ route('learning-path') }}" wire:navigate>
                    <flux:button variant="ghost">Kembali ke Roadmap</flux:button>
                </a>
            </div>
        </div>
    @else
        {{-- Questions --}}
        <form wire:submit="submit" class="space-y-6">
            @foreach ($questions as $index => $q)
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="font-medium mb-3">{{ $index + 1 }}. {{ $q['question'] }}</p>
                    <div class="space-y-2">
                        @foreach (['a', 'b', 'c', 'd'] as $opt)
                            <label class="flex items-center gap-2 p-2 rounded hover:bg-gray-50 cursor-pointer">
                                <input type="radio"
                                       wire:model="answers.{{ $index }}"
                                       value="{{ $opt }}"
                                       class="text-blue-600">
                                <span>{{ strtoupper($opt) }}. {{ $q["option_{$opt}"] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Kirim Jawaban</flux:button>
            </div>
        </form>
    @endif
</div>
