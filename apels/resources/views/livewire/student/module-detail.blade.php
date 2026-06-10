<?php

use App\Models\Module;
use App\Models\UserModuleProgress;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Module Detail — deskripsi, konten, tombol mulai latihan (Req 14.5).
 * Guard: redirect jika modul locked (Req 14.4).
 */
new #[Layout('components.layouts.app')] class extends Component {
    public ?Module $module = null;
    public bool $isUnlocked = false;
    public bool $isCompleted = false;
    public ?int $score = null;

    public function mount(int $module): void
    {
        $this->module = Module::findOrFail($module);

        $progress = UserModuleProgress::where('user_id', auth()->id())
            ->where('module_id', $this->module->id)
            ->first();

        if (!$progress || !$progress->is_unlocked) {
            // Guard: modul locked → redirect (Req 14.4)
            session()->flash('error', 'Modul ini masih terkunci.');
            $this->redirect(route('learning-path'), navigate: true);
            return;
        }

        $this->isUnlocked = $progress->is_unlocked;
        $this->isCompleted = $progress->is_completed;
        $this->score = $progress->score;
    }
}; ?>

<div class="max-w-3xl mx-auto py-8 px-4">
    @if ($module)
        <div class="mb-6">
            <a href="{{ route('learning-path') }}" wire:navigate class="text-blue-600 text-sm hover:underline">← Kembali ke Roadmap</a>
        </div>

        <h1 class="text-2xl font-bold mb-2">{{ $module->title }}</h1>
        <div class="flex items-center gap-2 mb-4">
            <span class="text-xs px-2 py-1 rounded bg-gray-100 capitalize">{{ $module->level }}</span>
            @if ($isCompleted)
                <span class="text-xs px-2 py-1 rounded bg-green-100 text-green-800">Completed {{ $score ? "({$score}%)" : '' }}</span>
            @else
                <span class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-800">In Progress</span>
            @endif
        </div>

        @if ($module->description)
            <div class="prose prose-sm max-w-none mb-6">
                <p>{{ $module->description }}</p>
            </div>
        @endif

        {{-- Render content sections from JSON --}}
        @if ($module->content && isset($module->content['sections']))
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">Materi</h3>
                <div class="space-y-2">
                    @foreach ($module->content['sections'] as $section)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <span>{{ $section['title'] ?? 'Section' }}</span>
                            @if (isset($section['estimated_minutes']))
                                <span class="text-xs text-gray-500">~{{ $section['estimated_minutes'] }} menit</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tombol mulai latihan --}}
        <div class="mt-6">
            <a href="{{ route('exercises', $module->id) }}" wire:navigate>
                <flux:button variant="primary">
                    {{ $isCompleted ? 'Ulangi Latihan' : 'Mulai Latihan' }}
                </flux:button>
            </a>
        </div>
    @endif
</div>
