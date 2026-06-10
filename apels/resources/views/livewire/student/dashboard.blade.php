<?php

use App\Models\DiagnosticResult;
use App\Models\Feedback;
use App\Models\UserLearningPath;
use App\Models\UserModuleProgress;
use App\Services\ReEvaluationService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Dashboard Mahasiswa — radar chart, feedback, module roadmap (Req 13, 16.2).
 *
 * Polling: aktif hanya saat speaking sedang diproses (Req 13.4).
 */
new #[Layout('components.layouts.app')] class extends Component {
    public array $skills = [];
    public ?string $pathKey = null;
    public ?string $pathLabel = null;
    public ?string $feedback = null;
    public array $modules = [];
    public int $progress = 0;
    public bool $speakingProcessing = false;
    public bool $shouldReEvaluate = false;
    public bool $hasTest = false;

    public function mount(): void
    {
        $this->loadData();
    }

    /**
     * Reload data — called by polling when speaking is processing.
     */
    public function loadData(): void
    {
        $userId = auth()->id();

        // Latest DiagnosticResult
        $diagnostic = DiagnosticResult::where('user_id', $userId)
            ->latest()
            ->first();

        if (!$diagnostic) {
            $this->hasTest = false;
            return;
        }

        $this->hasTest = true;
        $this->speakingProcessing = !$diagnostic->is_speaking_processed;

        // Skills for radar chart (Req 13.1)
        $this->skills = [
            'speaking'   => $diagnostic->speaking,
            'grammar'    => $diagnostic->grammar,
            'vocabulary' => $diagnostic->vocabulary,
        ];

        // Active learning path (Req 13.2)
        $learningPath = UserLearningPath::where('user_id', $userId)->first();
        $this->pathKey = $learningPath?->path_key;
        $this->pathLabel = $this->pathKey
            ? config("learning_paths.{$this->pathKey}.label", $this->pathKey)
            : null;

        // Latest feedback (Req 13.3)
        $latestFeedback = Feedback::where('user_id', $userId)->latest()->first();
        $this->feedback = $latestFeedback?->message;

        // Module progress (Req 13.5, 13.6)
        $moduleProgress = UserModuleProgress::where('user_id', $userId)
            ->with('module')
            ->get();

        $this->modules = $moduleProgress->map(fn ($mp) => [
            'title'        => $mp->module?->title ?? 'Unknown',
            'is_unlocked'  => $mp->is_unlocked,
            'is_completed' => $mp->is_completed,
            'score'        => $mp->score,
            'status'       => $this->resolveStatus($mp),
        ])->toArray();

        $total = count($this->modules);
        $completed = collect($this->modules)->where('is_completed', true)->count();
        $this->progress = $total > 0 ? (int) round($completed / $total * 100) : 0;

        // Re-evaluation suggestion (Req 16.2)
        $reEval = app(ReEvaluationService::class);
        $this->shouldReEvaluate = $reEval->shouldReEvaluateByProgress($userId)
            && $reEval->canTakeTestToday($userId);
    }

    private function resolveStatus($moduleProgress): string
    {
        if (!$moduleProgress->is_unlocked) return 'Locked';
        if ($moduleProgress->is_completed) return 'Completed';
        return 'In Progress';
    }
}; ?>

<div class="relative py-6">
    <!-- Decorative Background Blobs for Glassmorphism -->
    <div class="absolute top-0 -left-10 w-72 h-72 bg-indigo-500/10 dark:bg-indigo-600/5 rounded-full blur-3xl -z-10 pointer-events-none"></div>
    <div class="absolute bottom-20 -right-10 w-80 h-80 bg-violet-500/10 dark:bg-violet-600/5 rounded-full blur-3xl -z-10 pointer-events-none"></div>

    {{-- Polling aktif hanya saat speaking sedang diproses (Req 13.4) --}}
    @if ($speakingProcessing)
        <div wire:poll.5s="loadData" class="flex items-center gap-3 p-4 mb-6 bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-200 rounded-2xl backdrop-blur-md animate-pulse">
            <svg class="animate-spin h-5 w-5 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="font-medium text-sm">Hasil evaluasi speaking Anda sedang diproses oleh AI. Halaman akan diperbarui otomatis...</span>
        </div>
    @endif

    {{-- Re-evaluation suggestion (Req 16.2) --}}
    @if ($shouldReEvaluate)
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-800 dark:text-emerald-200 rounded-2xl backdrop-blur-md shadow-lg shadow-emerald-500/5">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🎉</span>
                <div>
                    <h4 class="font-semibold text-sm">Kemajuan Luar Biasa!</h4>
                    <p class="text-xs opacity-90">Anda direkomendasikan untuk mengambil Diagnostic Test ulang untuk memperbarui level dan roadmap belajar Anda.</p>
                </div>
            </div>
            <a href="{{ route('diagnostic') }}" class="inline-flex items-center justify-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all duration-300 hover:scale-[1.03] active:scale-[0.98]" wire:navigate>
                Mulai Tes Ulang
            </a>
        </div>
    @endif

    {{-- Empty state (Req 13.7) --}}
    @if (!$hasTest)
        <div class="flex flex-col items-center justify-center text-center py-20 px-4 bg-white/60 dark:bg-zinc-900/60 border border-zinc-200/50 dark:border-zinc-800/50 rounded-3xl backdrop-blur-md shadow-xl max-w-2xl mx-auto my-10 transition-all duration-300 hover:shadow-2xl">
            <div class="relative mb-6">
                <div class="absolute inset-0 bg-indigo-500/20 rounded-full blur-xl scale-120 animate-pulse"></div>
                <div class="relative w-20 h-20 bg-gradient-to-tr from-indigo-500 to-violet-500 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-zinc-800 dark:text-zinc-100 mb-2">Belum ada hasil Diagnostic Test</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 max-w-sm mb-8">Kerjakan Diagnostic Test pertamamu untuk mengukur kemampuan bahasa Inggrismu dan mendapatkan roadmap belajar personal.</p>
            <a href="{{ route('diagnostic') }}" wire:navigate>
                <button class="px-6 py-3 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-semibold text-sm rounded-xl shadow-lg shadow-indigo-500/25 transition-all duration-300 hover:scale-[1.03] active:scale-[0.98]">
                    Mulai Diagnostic Test
                </button>
            </a>
        </div>
    @else
        <!-- Welcome Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-extrabold text-zinc-800 dark:text-zinc-100">Selamat datang kembali, {{ auth()->user()->name }}!</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Berikut adalah ringkasan kemampuan dan perkembangan belajar Anda hari ini.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            {{-- Radar Chart (Req 13.1) --}}
            <div class="bg-white/80 dark:bg-zinc-900/80 border border-zinc-200/50 dark:border-zinc-800/50 backdrop-blur-md rounded-2xl shadow-xl p-6 transition-all duration-300 hover:shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-zinc-800 dark:text-zinc-100">Skill Overview</h3>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Berdasarkan Tes Terakhir</span>
                </div>
                <div id="radar-chart" class="flex justify-center" wire:ignore></div>
            </div>

            {{-- Path Label + Feedback (Req 13.2, 13.3) --}}
            <div class="flex flex-col gap-6">
                <!-- Learning Path Info Card -->
                <div class="bg-gradient-to-br from-indigo-500 to-violet-600 text-white rounded-2xl shadow-xl p-6 relative overflow-hidden transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-500/10">
                    <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
                    <div class="relative z-10">
                        <span class="text-xs font-bold uppercase tracking-wider bg-white/20 px-2.5 py-1 rounded-full backdrop-blur-md">Active Learning Path</span>
                        <h2 class="text-2xl font-black mt-3 mb-2">{{ $pathLabel ?? 'Memuat Path...' }}</h2>
                        <p class="text-xs text-white/85 leading-relaxed">Roadmap belajar Anda telah dikustomisasi secara otomatis oleh AI agar sesuai dengan tingkat kemampuan linguistik Anda saat ini.</p>
                        <div class="mt-6 flex justify-start">
                            <a href="{{ route('learning-path') }}" class="inline-flex items-center gap-2 text-xs font-semibold bg-white text-indigo-700 px-4 py-2 rounded-xl shadow-md hover:bg-zinc-50 transition-all duration-300 hover:scale-[1.03]" wire:navigate>
                                <span>Buka Roadmap Belajar</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Feedback Card -->
                <div class="bg-white/80 dark:bg-zinc-900/80 border border-zinc-200/50 dark:border-zinc-800/50 backdrop-blur-md rounded-2xl shadow-xl p-6 flex-1 flex flex-col transition-all duration-300 hover:shadow-2xl">
                    <div class="flex items-center gap-2.5 mb-4">
                        <div class="w-9 h-9 bg-indigo-500/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                            </svg>
                        </div>
                        <h4 class="font-bold text-zinc-800 dark:text-zinc-100">Feedback Rekomendasi</h4>
                    </div>
                    @if ($feedback)
                        <div class="relative flex-1 bg-zinc-50 dark:bg-zinc-950/40 p-4 rounded-xl border border-zinc-100 dark:border-zinc-900">
                            <span class="absolute -top-3 left-4 text-4xl text-indigo-500/20 font-serif">“</span>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed italic z-10 relative pl-2 pr-2">{{ $feedback }}</p>
                        </div>
                    @else
                        <div class="flex-1 flex items-center justify-center py-6 text-center text-xs text-zinc-500 dark:text-zinc-500 italic">
                            Belum ada feedback khusus dari dosen atau AI.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Progress bar (Req 13.6) --}}
        <div class="bg-white/80 dark:bg-zinc-900/80 border border-zinc-200/50 dark:border-zinc-800/50 backdrop-blur-md rounded-2xl shadow-xl p-6 mb-8 transition-all duration-300 hover:shadow-2xl">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-bold text-zinc-800 dark:text-zinc-100">Progress Modul</h3>
                <span class="text-xs font-semibold bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 px-2.5 py-1 rounded-full">
                    {{ $progress }}% Selesai
                </span>
            </div>
            
            <div class="relative w-full bg-zinc-200 dark:bg-zinc-800 rounded-full h-3.5 overflow-hidden shadow-inner">
                <!-- Glowing Progress Track -->
                <div class="bg-gradient-to-r from-indigo-500 via-indigo-600 to-violet-600 h-full rounded-full transition-all duration-1000 ease-out relative" style="width: {{ $progress }}%">
                    <!-- Shimmer animation -->
                    <div class="absolute inset-0 bg-linear-to-r from-transparent via-white/25 to-transparent -translate-x-full animate-shimmer"></div>
                </div>
            </div>
            
            <div class="flex justify-between items-center mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                <span>Mulai belajar</span>
                <span>{{ collect($modules)->where('is_completed', true)->count() }} dari {{ count($modules) }} Modul Terselesaikan</span>
            </div>
        </div>

        {{-- Module list (Req 13.5) --}}
        <div class="bg-white/80 dark:bg-zinc-900/80 border border-zinc-200/50 dark:border-zinc-800/50 backdrop-blur-md rounded-2xl shadow-xl p-6 transition-all duration-300 hover:shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-bold text-zinc-800 dark:text-zinc-100">Modul Pembelajaran</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Selesaikan setiap modul untuk membuka modul berikutnya.</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($modules as $module)
                    @php
                        $isLocked = $module['status'] === 'Locked';
                        $isCompleted = $module['status'] === 'Completed';
                        $isInProgress = $module['status'] === 'In Progress';
                    @endphp
                    
                    <div class="group flex items-center justify-between p-4 rounded-xl border transition-all duration-300 
                        {{ $isLocked 
                            ? 'bg-zinc-50/50 dark:bg-zinc-950/20 border-zinc-200/40 dark:border-zinc-900 opacity-60' 
                            : 'bg-white dark:bg-zinc-900 border-zinc-200/70 dark:border-zinc-800 hover:border-indigo-500/30 hover:shadow-lg dark:hover:border-indigo-500/20 hover:scale-[1.01]' 
                        }}">
                        
                        <div class="flex items-center gap-3">
                            <!-- Status Icon -->
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-300
                                {{ $isCompleted ? 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400' : '' }}
                                {{ $isInProgress ? 'bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 animate-pulse' : '' }}
                                {{ $isLocked ? 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-600' : '' }}
                            ">
                                @if ($isCompleted)
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                @elseif ($isInProgress)
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                @endif
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-semibold {{ $isLocked ? 'text-zinc-500 dark:text-zinc-500' : 'text-zinc-800 dark:text-zinc-200' }}">
                                    {{ $module['title'] }}
                                </h4>
                                <span class="text-[11px] font-medium tracking-wide
                                    {{ $isCompleted ? 'text-emerald-600 dark:text-emerald-400' : '' }}
                                    {{ $isInProgress ? 'text-indigo-600 dark:text-indigo-400' : '' }}
                                    {{ $isLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}
                                ">
                                    @if ($isCompleted)
                                        Selesai {{ $module['score'] ? "({$module['score']}%)" : '' }}
                                    @elseif ($isInProgress)
                                        Sedang Dipelajari
                                    @else
                                        Terkunci
                                    @endif
                                </span>
                            </div>
                        </div>

                        <!-- Action Button -->
                        @if (!$isLocked)
                            <a href="{{ route('learning-path') }}" class="p-2 rounded-lg text-zinc-400 dark:text-zinc-500 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-colors" wire:navigate>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ApexCharts radar chart script (Req 13.1) --}}
    @if ($hasTest)
        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        <script>
            function initChart() {
                const chartEl = document.querySelector('#radar-chart');
                if (!chartEl) return;
                
                chartEl.innerHTML = '';
                
                const isDark = document.documentElement.classList.contains('dark');
                
                const options = {
                    series: [
                        {
                            name: 'Level Anda',
                            data: [{{ $skills['speaking'] }}, {{ $skills['grammar'] }}, {{ $skills['vocabulary'] }}]
                        },
                        {
                            name: 'Target Kelulusan',
                            data: [80, 80, 80]
                        }
                    ],
                    chart: { 
                        type: 'radar', 
                        height: 310,
                        toolbar: { show: false },
                        background: 'transparent',
                        dropShadow: {
                            enabled: true,
                            blur: 8,
                            left: 1,
                            top: 1,
                            opacity: 0.05
                        }
                    },
                    grid: {
                        padding: {
                            top: 0,
                            right: 0,
                            bottom: 0,
                            left: 0
                        }
                    },
                    theme: {
                        mode: isDark ? 'dark' : 'light'
                    },
                    xaxis: { 
                        categories: ['Speaking', 'Grammar', 'Vocabulary'],
                        labels: {
                            style: {
                                colors: isDark ? ['#a1a1aa', '#a1a1aa', '#a1a1aa'] : ['#4b5563', '#4b5563', '#4b5563'],
                                fontSize: '12px',
                                fontFamily: 'Instrument Sans, sans-serif',
                                fontWeight: 600
                            }
                        }
                    },
                    yaxis: { 
                        min: 0, 
                        max: 100,
                        tickAmount: 5,
                        show: false
                    },
                    colors: ['#6366f1', isDark ? '#3f3f46' : '#cbd5e1'],
                    stroke: { width: 2, curve: 'smooth' },
                    fill: { opacity: 0.15 },
                    markers: { size: 4, colors: ['#6366f1', isDark ? '#3f3f46' : '#cbd5e1'] },
                    tooltip: {
                        theme: isDark ? 'dark' : 'light',
                        y: {
                            formatter: function(val) { return val + " / 100" }
                        }
                    },
                    legend: {
                        position: 'bottom',
                        fontFamily: 'Instrument Sans, sans-serif',
                        labels: {
                            colors: isDark ? '#e2e8f0' : '#1e293b'
                        }
                    }
                };
                
                const chart = new ApexCharts(chartEl, options);
                chart.render();
            }

            document.addEventListener('DOMContentLoaded', initChart);
            document.addEventListener('livewire:navigated', initChart);
        </script>
        @endpush
    @endif
</div>
