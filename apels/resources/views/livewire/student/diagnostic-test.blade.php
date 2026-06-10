<?php

use App\Services\DiagnosticService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * DiagnosticTest — Wizard 3 langkah (Req 4.1):
 *   Step 1: Grammar (text answers)
 *   Step 2: Vocabulary (text answers)
 *   Step 3: Speaking (audio upload)
 *
 * Submit final → DiagnosticService::process → redirect Dashboard.
 */
new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public int $currentStep = 1;

    // Step 1: Grammar answers (min 3 elements)
    public array $grammarAnswers = ['', '', ''];

    // Step 2: Vocabulary answers (min 3 elements)
    public array $vocabAnswers = ['', '', ''];

    // Step 3: Audio file
    public $audioFile = null;

    public ?string $errorMessage = null;
    public bool $isSubmitting = false;

    /**
     * Add another answer field to grammar step.
     */
    public function addGrammarField(): void
    {
        if (count($this->grammarAnswers) < 10) {
            $this->grammarAnswers[] = '';
        }
    }

    /**
     * Add another answer field to vocabulary step.
     */
    public function addVocabField(): void
    {
        if (count($this->vocabAnswers) < 10) {
            $this->vocabAnswers[] = '';
        }
    }

    /**
     * Navigate to next step with validation.
     */
    public function nextStep(): void
    {
        if ($this->currentStep === 1) {
            $this->validateGrammar();
        } elseif ($this->currentStep === 2) {
            $this->validateVocab();
        }

        if ($this->currentStep < 3) {
            $this->currentStep++;
        }
    }

    /**
     * Navigate to previous step.
     */
    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    /**
     * Submit the diagnostic test.
     */
    public function submit(): void
    {
        $this->isSubmitting = true;
        $this->errorMessage = null;

        // Guard: cek daily limit (Req 3) — second line of defense
        // (first line: GET /diagnostic route middleware)
        $alreadyTested = \App\Models\DiagnosticResult::where('user_id', auth()->id())
            ->whereDate('created_at', today())
            ->exists();

        if ($alreadyTested) {
            $this->errorMessage = 'Anda sudah mengerjakan tes hari ini. Coba lagi besok.';
            $this->isSubmitting = false;
            return;
        }

        // Validate audio
        $this->validate([
            'audioFile' => ['required', 'file', 'mimes:wav,mp3,webm,ogg', 'max:' . (config('apels.audio_max_mb', 10) * 1024)],
        ]);

        try {
            // Combine text answers
            $textAnswers = array_merge(
                array_filter($this->grammarAnswers, fn($a) => trim($a) !== ''),
                array_filter($this->vocabAnswers, fn($a) => trim($a) !== ''),
            );

            // Store audio to private disk
            $audioPath = $this->audioFile->store('diagnostic-audio', 'private');

            // Process via DiagnosticService
            $service = app(DiagnosticService::class);
            $result = $service->process(auth()->id(), [
                'text_answers' => $textAnswers,
                'audio_path'   => $audioPath,
            ]);

            session()->flash('status', $result['message']);
            $this->redirect(route('dashboard'), navigate: true);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Terjadi kesalahan saat memproses tes. Silakan coba lagi.';
            $this->isSubmitting = false;
        }
    }

    private function validateGrammar(): void
    {
        $filled = array_filter($this->grammarAnswers, fn($a) => strlen(trim($a)) >= 5);
        if (count($filled) < 3) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'grammarAnswers' => 'Minimal 3 jawaban grammar dengan panjang minimal 5 karakter.',
            ]);
        }
        foreach ($this->grammarAnswers as $i => $answer) {
            if (strlen($answer) > 500) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'grammarAnswers' => "Jawaban " . ($i + 1) . " melebihi 500 karakter.",
                ]);
            }
        }
    }

    private function validateVocab(): void
    {
        $filled = array_filter($this->vocabAnswers, fn($a) => strlen(trim($a)) >= 5);
        if (count($filled) < 3) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'vocabAnswers' => 'Minimal 3 jawaban vocabulary dengan panjang minimal 5 karakter.',
            ]);
        }
        foreach ($this->vocabAnswers as $i => $answer) {
            if (strlen($answer) > 500) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'vocabAnswers' => "Jawaban " . ($i + 1) . " melebihi 500 karakter.",
                ]);
            }
        }
    }
}; ?>

<div class="relative py-8">
    <!-- Decorative background blobs -->
    <div class="absolute top-0 -left-10 w-72 h-72 bg-indigo-500/10 dark:bg-indigo-600/5 rounded-full blur-3xl -z-10 pointer-events-none"></div>
    <div class="absolute bottom-20 -right-10 w-80 h-80 bg-violet-500/10 dark:bg-violet-600/5 rounded-full blur-3xl -z-10 pointer-events-none"></div>

    <div class="max-w-2xl mx-auto px-4">
        <!-- Title Section -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-zinc-800 dark:text-zinc-100 tracking-tight">Diagnostic Test</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">Ukur kemampuan bahasa Inggrismu secara komprehensif melalui 3 tahap evaluasi.</p>
        </div>

        <!-- Progress indicator stepper -->
        <div class="relative max-w-lg mx-auto mb-10">
            <!-- Connecting Line background -->
            <div class="absolute top-1/2 left-0 w-full h-1 bg-zinc-200 dark:bg-zinc-800 -translate-y-1/2 -z-10 rounded-full"></div>
            <!-- Connecting Line active fill -->
            <div class="absolute top-1/2 left-0 h-1 bg-gradient-to-r from-indigo-500 to-violet-600 -translate-y-1/2 -z-10 rounded-full transition-all duration-500" 
                 style="width: {{ (($currentStep - 1) / 2) * 100 }}%"></div>

            <!-- Stepper circles -->
            <div class="flex justify-between items-center">
                @foreach ([1 => 'Grammar', 2 => 'Vocabulary', 3 => 'Speaking'] as $step => $label)
                    @php
                        $isCompleted = $currentStep > $step;
                        $isActive = $currentStep === $step;
                        $isPending = $currentStep < $step;
                    @endphp
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-500
                            {{ $isCompleted ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-lg shadow-emerald-500/20' : '' }}
                            {{ $isActive ? 'bg-gradient-to-r from-indigo-500 to-violet-600 text-white ring-4 ring-indigo-500/20 shadow-lg shadow-indigo-500/30 scale-110' : '' }}
                            {{ $isPending ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-400 dark:text-zinc-500' : '' }}
                        ">
                            @if ($isCompleted)
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                </svg>
                            @else
                                {{ $step }}
                            @endif
                        </div>
                        <span class="mt-2 text-xs font-bold transition-colors duration-300
                            {{ $isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-400 dark:text-zinc-500' }}
                        ">
                            {{ $label }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Form Card -->
        <div class="bg-white/80 dark:bg-zinc-900/80 border border-zinc-200/50 dark:border-zinc-800/50 backdrop-blur-md rounded-3xl p-8 shadow-xl transition-all duration-300 hover:shadow-2xl">
            @if ($errorMessage)
                <div class="flex items-center gap-3 p-4 mb-6 bg-red-500/10 border border-red-500/20 text-red-800 dark:text-red-200 rounded-2xl">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <span class="text-xs font-semibold">{{ $errorMessage }}</span>
                </div>
            @endif

            <!-- Step 1: Grammar -->
            @if ($currentStep === 1)
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 rounded-xl flex items-center justify-center">
                            <span class="font-extrabold text-sm">G</span>
                        </div>
                        <h2 class="text-xl font-bold text-zinc-800 dark:text-zinc-100">Step 1: Grammar Evaluation</h2>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-6 leading-relaxed">Tulis setidaknya 3 jawaban atau kalimat bahasa Inggris yang benar secara tata bahasa (grammar). AI akan mengevaluasi tingkat akurasi struktur kalimat Anda.</p>

                    <div class="space-y-4">
                        @foreach ($grammarAnswers as $index => $answer)
                            <div class="p-4 bg-zinc-50/50 dark:bg-zinc-950/20 border border-zinc-150 dark:border-zinc-900 rounded-2xl relative transition-all hover:border-indigo-500/20">
                                <flux:textarea
                                    wire:model.live="grammarAnswers.{{ $index }}"
                                    label="Jawaban {{ $index + 1 }}"
                                    placeholder="Tulis kalimat bahasa Inggris Anda di sini..."
                                    rows="2"
                                />
                                <div class="mt-1.5 flex justify-between items-center text-[10px] text-zinc-400">
                                    <span>Minimal 5 karakter, maksimal 500 karakter</span>
                                    <span class="{{ strlen($answer ?? '') > 500 ? 'text-red-500 font-bold' : '' }}">
                                        {{ strlen($answer ?? '') }}/500
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-between items-center">
                        @if (count($grammarAnswers) < 10)
                            <button wire:click="addGrammarField" type="button" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Tambah Jawaban
                            </button>
                        @else
                            <div></div>
                        @endif
                        
                        <span class="text-xs text-zinc-400 font-medium">{{ count($grammarAnswers) }}/10 fields</span>
                    </div>

                    @error('grammarAnswers')
                        <div class="mt-6 p-4 bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 rounded-2xl flex items-center gap-2 text-xs font-semibold animate-pulse">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="flex justify-end mt-8 border-t border-zinc-100 dark:border-zinc-900 pt-6">
                        <flux:button wire:click="nextStep" variant="primary" class="w-full sm:w-auto bg-gradient-to-r from-indigo-500 to-violet-600 text-white font-semibold shadow-md shadow-indigo-500/20">
                            Selanjutnya →
                        </flux:button>
                    </div>
                </div>
            @endif

            <!-- Step 2: Vocabulary -->
            @if ($currentStep === 2)
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 rounded-xl flex items-center justify-center">
                            <span class="font-extrabold text-sm">V</span>
                        </div>
                        <h2 class="text-xl font-bold text-zinc-800 dark:text-zinc-100">Step 2: Vocabulary Evaluation</h2>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-6 leading-relaxed">Tulis setidaknya 3 jawaban kosakata atau istilah bahasa Inggris dengan deskripsi/kalimat penggunaannya. Kami akan mengevaluasi kekayaan perbendaharaan kata (vocabulary) Anda.</p>

                    <div class="space-y-4">
                        @foreach ($vocabAnswers as $index => $answer)
                            <div class="p-4 bg-zinc-50/50 dark:bg-zinc-950/20 border border-zinc-150 dark:border-zinc-900 rounded-2xl relative transition-all hover:border-indigo-500/20">
                                <flux:textarea
                                    wire:model.live="vocabAnswers.{{ $index }}"
                                    label="Jawaban {{ $index + 1 }}"
                                    placeholder="Tulis kalimat kosakata Anda di sini..."
                                    rows="2"
                                />
                                <div class="mt-1.5 flex justify-between items-center text-[10px] text-zinc-400">
                                    <span>Minimal 5 karakter, maksimal 500 karakter</span>
                                    <span class="{{ strlen($answer ?? '') > 500 ? 'text-red-500 font-bold' : '' }}">
                                        {{ strlen($answer ?? '') }}/500
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-between items-center">
                        @if (count($vocabAnswers) < 10)
                            <button wire:click="addVocabField" type="button" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 rounded-xl transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Tambah Jawaban
                            </button>
                        @else
                            <div></div>
                        @endif
                        
                        <span class="text-xs text-zinc-400 font-medium">{{ count($vocabAnswers) }}/10 fields</span>
                    </div>

                    @error('vocabAnswers')
                        <div class="mt-6 p-4 bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 rounded-2xl flex items-center gap-2 text-xs font-semibold animate-pulse">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="flex justify-between gap-4 mt-8 border-t border-zinc-100 dark:border-zinc-900 pt-6">
                        <flux:button wire:click="previousStep" variant="ghost" class="font-semibold">
                            ← Kembali
                        </flux:button>
                        <flux:button wire:click="nextStep" variant="primary" class="bg-gradient-to-r from-indigo-500 to-violet-600 text-white font-semibold shadow-md shadow-indigo-500/20">
                            Selanjutnya →
                        </flux:button>
                    </div>
                </div>
            @endif

            <!-- Step 3: Speaking -->
            @if ($currentStep === 3)
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 rounded-xl flex items-center justify-center">
                            <span class="font-extrabold text-sm">S</span>
                        </div>
                        <h2 class="text-xl font-bold text-zinc-800 dark:text-zinc-100">Step 3: Speaking & Pronunciation</h2>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-6 leading-relaxed">Berbicaralah dalam bahasa Inggris selama 30-60 detik untuk menceritakan perkenalan diri Anda, hobi, atau pekerjaan Anda. AI akan menganalisis pelafalan (*pronunciation*) dan kefasihan (*fluency*) Anda.</p>

                    <!-- Embedded interactive audio recorder -->
                    <div x-data="audioRecorder()" class="mt-4">
                        <!-- Tab navigation -->
                        <div class="flex border-b border-zinc-200 dark:border-zinc-800 mb-6">
                            <button type="button" @click="tab = 'record'" :class="tab === 'record' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="flex-1 py-3 border-b-2 text-center text-xs font-semibold transition-all">
                                🎙️ Rekam Langsung (Rekomendasi)
                            </button>
                            <button type="button" @click="tab = 'upload'" :class="tab === 'upload' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="flex-1 py-3 border-b-2 text-center text-xs font-semibold transition-all">
                                📁 Unggah File Audio
                            </button>
                        </div>

                        <!-- Record Tab -->
                        <div x-show="tab === 'record'" class="flex flex-col items-center justify-center p-8 bg-zinc-50 dark:bg-zinc-950/40 rounded-2xl border border-zinc-150 dark:border-zinc-900">
                            <!-- Idle State -->
                            <div x-show="status === 'idle'" class="text-center">
                                <button type="button" @click="startRecording" class="w-20 h-20 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full flex items-center justify-center shadow-lg shadow-indigo-600/30 hover:scale-105 active:scale-95 transition-all duration-300">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                                    </svg>
                                </button>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mt-4">Klik untuk Mulai Rekam</p>
                                <p class="text-xs text-zinc-500 mt-1">Pastikan mikrofon aktif dan Anda berada di lingkungan yang tenang.</p>
                            </div>

                            <!-- Recording State -->
                            <div x-show="status === 'recording'" class="text-center w-full" x-cloak>
                                <div class="flex items-center justify-center gap-1.5 h-10 mb-4">
                                    <div class="w-1 bg-red-500 rounded-full animate-[pulse_0.8s_infinite] h-8"></div>
                                    <div class="w-1 bg-red-500 rounded-full animate-[pulse_0.8s_infinite_0.15s] h-5"></div>
                                    <div class="w-1 bg-red-500 rounded-full animate-[pulse_0.8s_infinite_0.3s] h-10"></div>
                                    <div class="w-1 bg-red-500 rounded-full animate-[pulse_0.8s_infinite_0.45s] h-6"></div>
                                    <div class="w-1 bg-red-500 rounded-full animate-[pulse_0.8s_infinite_0.6s] h-8"></div>
                                </div>
                                
                                <div class="text-3xl font-black font-mono text-red-600 dark:text-red-500 mb-2" x-text="formatTime(seconds)">00:00</div>
                                <p class="text-xs text-zinc-500 mb-6 animate-pulse">Sedang Merekam Suara Anda...</p>
                                
                                <button type="button" @click="stopRecording" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-md shadow-red-600/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
                                    Stop & Simpan
                                </button>
                            </div>

                            <!-- Finished / Ready State -->
                            <div x-show="status === 'finished'" class="text-center w-full" x-cloak>
                                <div class="w-14 h-14 bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <h4 class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Rekaman Suara Disimpan!</h4>
                                <p class="text-xs text-zinc-500 mt-1 mb-6">Dengarkan kembali suara Anda untuk memastikan kualitas audio jelas.</p>
                                
                                <audio :src="audioUrl" controls class="mx-auto mb-6 w-full max-w-md"></audio>
                                
                                <button type="button" @click="startRecording" class="px-4 py-2 bg-zinc-200 hover:bg-zinc-300 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 font-semibold text-xs rounded-xl transition-all">
                                    Rekam Ulang
                                </button>
                            </div>
                        </div>

                        <!-- Upload Tab -->
                        <div x-show="tab === 'upload'" class="p-8 bg-zinc-50 dark:bg-zinc-950/40 rounded-2xl border border-zinc-150 dark:border-zinc-900" x-cloak>
                            <div class="flex flex-col items-center justify-center border-2 border-dashed border-zinc-300 dark:border-zinc-800 rounded-xl p-8 hover:border-indigo-500/50 transition-colors cursor-pointer relative"
                                 @click="$refs.fileInput.click()">
                                <svg class="w-10 h-10 text-zinc-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 text-center">Klik atau Tarik File Audio ke Sini</p>
                                <p class="text-[11px] text-zinc-500 mt-1">WAV, MP3, WEBM, atau OGG (Maks {{ config('apels.audio_max_mb', 10) }} MB)</p>
                                
                                <input
                                    type="file"
                                    x-ref="fileInput"
                                    @change="handleFileUpload"
                                    accept=".wav,.mp3,.webm,.ogg"
                                    class="hidden"
                                />
                            </div>

                            <template x-if="uploadedFileName">
                                <div class="mt-4 p-3 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2 text-zinc-700 dark:text-zinc-300">
                                        📁 <span x-text="uploadedFileName" class="font-semibold truncate max-w-xs"></span>
                                    </div>
                                    <button type="button" @click="removeUploadedFile" class="text-red-500 hover:text-red-700 font-bold">
                                        Hapus
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- Livewire Upload Progress Indicator -->
                        <div x-show="isUploading" class="mt-4 w-full bg-zinc-100 dark:bg-zinc-900 rounded-full h-2 overflow-hidden border border-zinc-200/50 dark:border-zinc-800/50" x-cloak>
                            <div class="bg-indigo-600 h-full rounded-full transition-all duration-300" :style="`width: ${uploadProgress}%`"></div>
                        </div>
                        <div x-show="isUploading" class="text-[10px] text-zinc-500 mt-1 text-right" x-text="`Mengunggah audio: ${uploadProgress}%`" x-cloak></div>
                    </div>

                    @error('audioFile')
                        <div class="mt-6 p-4 bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 rounded-2xl flex items-center gap-2 text-xs font-semibold animate-pulse">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="flex justify-between gap-4 mt-8 border-t border-zinc-100 dark:border-zinc-900 pt-6">
                        <flux:button wire:click="previousStep" variant="ghost" class="font-semibold" :disabled="$isSubmitting">
                            ← Kembali
                        </flux:button>
                        <flux:button wire:click="submit" variant="primary" class="flex-1 sm:flex-none bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-bold shadow-md shadow-indigo-500/20 transition-all duration-300" wire:loading.attr="disabled" :disabled="$isSubmitting || isUploading">
                            <span wire:loading.remove wire:target="submit">Kirim Diagnostic Test</span>
                            <span wire:loading wire:target="submit" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Memproses...
                            </span>
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Alpine.js recorder logic -->
    <script>
        function registerAudioRecorder() {
            if (window.Alpine) {
                if (!Alpine.data('audioRecorder')) {
                    Alpine.data('audioRecorder', () => ({
                        tab: 'record',
                        status: 'idle',
                        mediaRecorder: null,
                        audioChunks: [],
                        audioUrl: '',
                        seconds: 0,
                        timer: null,
                        isUploading: false,
                        uploadProgress: 0,
                        uploadedFileName: '',

                        formatTime(secs) {
                            const m = Math.floor(secs / 60).toString().padStart(2, '0');
                            const s = (secs % 60).toString().padStart(2, '0');
                            return `${m}:${s}`;
                        },

                        async startRecording() {
                            try {
                                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                                this.audioChunks = [];
                                this.mediaRecorder = new MediaRecorder(stream);
                                
                                this.mediaRecorder.ondataavailable = (e) => {
                                    if (e.data.size > 0) {
                                        this.audioChunks.push(e.data);
                                    }
                                };

                                this.mediaRecorder.onstop = () => {
                                    const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
                                    this.audioUrl = URL.createObjectURL(blob);
                                    this.uploadAudioBlob(blob);
                                };

                                this.mediaRecorder.start();
                                this.status = 'recording';
                                this.seconds = 0;
                                this.timer = setInterval(() => {
                                    this.seconds++;
                                    if (this.seconds >= 120) {
                                        this.stopRecording();
                                    }
                                }, 1000);
                            } catch (err) {
                                alert('Tidak dapat mengakses mikrofon. Pastikan Anda memberikan izin akses mikrofon di browser.');
                                console.error(err);
                            }
                        },

                        stopRecording() {
                            if (this.mediaRecorder && this.status === 'recording') {
                                this.mediaRecorder.stop();
                                this.mediaRecorder.stream.getTracks().forEach(track => track.stop());
                                clearInterval(this.timer);
                                this.status = 'finished';
                            }
                        },

                        uploadAudioBlob(blob) {
                            this.isUploading = true;
                            this.uploadProgress = 0;
                            
                            const file = new File([blob], 'recording.webm', { type: 'audio/webm' });
                            
                            @this.upload('audioFile', file,
                                (uploadedName) => {
                                    this.isUploading = false;
                                    this.uploadProgress = 100;
                                },
                                (error) => {
                                    this.isUploading = false;
                                    alert('Gagal mengunggah rekaman. Silakan coba lagi.');
                                },
                                (event) => {
                                    this.uploadProgress = event.detail.progress;
                                }
                            );
                        },

                        handleFileUpload(event) {
                            const file = event.target.files[0];
                            if (!file) return;

                            this.uploadedFileName = file.name;
                            this.isUploading = true;
                            this.uploadProgress = 0;

                            @this.upload('audioFile', file,
                                (uploadedName) => {
                                    this.isUploading = false;
                                    this.uploadProgress = 100;
                                },
                                (error) => {
                                    this.isUploading = false;
                                    alert('Gagal mengunggah file. Silakan coba lagi.');
                                },
                                (event) => {
                                    this.uploadProgress = event.detail.progress;
                                }
                            );
                        },

                        removeUploadedFile() {
                            this.uploadedFileName = '';
                            this.audioUrl = '';
                            @this.set('audioFile', null);
                        }
                    }));
                }
            } else {
                document.addEventListener('alpine:init', () => {
                    Alpine.data('audioRecorder', () => ({
                        tab: 'record',
                        status: 'idle',
                        mediaRecorder: null,
                        audioChunks: [],
                        audioUrl: '',
                        seconds: 0,
                        timer: null,
                        isUploading: false,
                        uploadProgress: 0,
                        uploadedFileName: '',

                        formatTime(secs) {
                            const m = Math.floor(secs / 60).toString().padStart(2, '0');
                            const s = (secs % 60).toString().padStart(2, '0');
                            return `${m}:${s}`;
                        },

                        async startRecording() {
                            try {
                                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                                this.audioChunks = [];
                                this.mediaRecorder = new MediaRecorder(stream);
                                
                                this.mediaRecorder.ondataavailable = (e) => {
                                    if (e.data.size > 0) {
                                        this.audioChunks.push(e.data);
                                    }
                                };

                                this.mediaRecorder.onstop = () => {
                                    const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
                                    this.audioUrl = URL.createObjectURL(blob);
                                    this.uploadAudioBlob(blob);
                                };

                                this.mediaRecorder.start();
                                this.status = 'recording';
                                this.seconds = 0;
                                this.timer = setInterval(() => {
                                    this.seconds++;
                                    if (this.seconds >= 120) {
                                        this.stopRecording();
                                    }
                                }, 1000);
                            } catch (err) {
                                alert('Tidak dapat mengakses mikrofon. Pastikan Anda memberikan izin akses mikrofon di browser.');
                                console.error(err);
                            }
                        },

                        stopRecording() {
                            if (this.mediaRecorder && this.status === 'recording') {
                                this.mediaRecorder.stop();
                                this.mediaRecorder.stream.getTracks().forEach(track => track.stop());
                                clearInterval(this.timer);
                                this.status = 'finished';
                            }
                        },

                        uploadAudioBlob(blob) {
                            this.isUploading = true;
                            this.uploadProgress = 0;
                            
                            const file = new File([blob], 'recording.webm', { type: 'audio/webm' });
                            
                            @this.upload('audioFile', file,
                                (uploadedName) => {
                                    this.isUploading = false;
                                    this.uploadProgress = 100;
                                },
                                (error) => {
                                    this.isUploading = false;
                                    alert('Gagal mengunggah rekaman. Silakan coba lagi.');
                                },
                                (event) => {
                                    this.uploadProgress = event.detail.progress;
                                }
                            );
                        },

                        handleFileUpload(event) {
                            const file = event.target.files[0];
                            if (!file) return;

                            this.uploadedFileName = file.name;
                            this.isUploading = true;
                            this.uploadProgress = 0;

                            @this.upload('audioFile', file,
                                (uploadedName) => {
                                    this.isUploading = false;
                                    this.uploadProgress = 100;
                                },
                                (error) => {
                                    this.isUploading = false;
                                    alert('Gagal mengunggah file. Silakan coba lagi.');
                                },
                                (event) => {
                                    this.uploadProgress = event.detail.progress;
                                }
                            );
                        },

                        removeUploadedFile() {
                            this.uploadedFileName = '';
                            this.audioUrl = '';
                            @this.set('audioFile', null);
                        }
                    }));
                });
            }
        }
        registerAudioRecorder();
    </script>
</div>
