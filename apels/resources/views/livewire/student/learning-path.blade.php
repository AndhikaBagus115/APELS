<?php

use App\Models\UserLearningPath;
use App\Models\UserModuleProgress;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Learning Roadmap — menampilkan modul terurut dengan status (Req 14.1-14.4).
 */
new #[Layout('components.layouts.app')] class extends Component {
    public array $modules = [];
    public ?string $pathKey = null;
    public ?string $pathLabel = null;
    public ?string $pathColor = null;
    public ?string $pathCondition = null;
    public int $completedCount = 0;
    public int $totalCount = 0;
    public int $progressPercent = 0;

    public function mount(): void
    {
        $this->loadModules();
    }

    public function loadModules(): void
    {
        $userId = auth()->id();

        // Active learning path info
        $learningPath = UserLearningPath::where('user_id', $userId)->first();
        $this->pathKey = $learningPath?->path_key;
        if ($this->pathKey) {
            $cfg = config("learning_paths.{$this->pathKey}");
            $this->pathLabel = $cfg['label'] ?? $this->pathKey;
            $this->pathColor = $cfg['color'] ?? 'indigo';
            $this->pathCondition = $cfg['condition'] ?? null;
        }

        $progress = UserModuleProgress::where('user_id', $userId)
            ->with('module')
            ->get()
            ->sortBy(fn ($mp) => $mp->module?->order_index ?? 0);

        $this->modules = $progress->map(fn ($mp) => [
            'id'           => $mp->module_id,
            'title'        => $mp->module?->title ?? 'Unknown',
            'description'  => $mp->module?->description ?? '',
            'level'        => $mp->module?->level ?? '',
            'is_unlocked'  => $mp->is_unlocked,
            'is_completed' => $mp->is_completed,
            'score'        => $mp->score,
            'status'       => match (true) {
                !$mp->is_unlocked => 'Locked',
                $mp->is_completed => 'Completed',
                default           => 'In Progress',
            },
        ])->values()->toArray();

        $this->totalCount = count($this->modules);
        $this->completedCount = collect($this->modules)->where('is_completed', true)->count();
        $this->progressPercent = $this->totalCount > 0
            ? (int) round($this->completedCount / $this->totalCount * 100)
            : 0;
    }
}; ?>

<div class="relative py-8">
    <!-- Decorative background blobs -->
    <div class="absolute top-10 -left-10 w-72 h-72 bg-indigo-500/10 dark:bg-indigo-600/5 rounded-full blur-3xl -z-10 pointer-events-none"></div>
    <div class="absolute bottom-20 -right-10 w-80 h-80 bg-violet-500/10 dark:bg-violet-600/5 rounded-full blur-3xl -z-10 pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/2 w-60 h-60 bg-emerald-500/5 dark:bg-emerald-600/5 rounded-full blur-3xl -z-10 pointer-events-none -translate-x-1/2 -translate-y-1/2"></div>

    <div class="max-w-3xl mx-auto px-4">
        <!-- Title Section -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-zinc-800 dark:text-zinc-100 tracking-tight">Learning Roadmap</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-2">Ikuti perjalanan belajarmu dari awal hingga mahir.</p>
        </div>

        @if (empty($modules))
            <!-- Empty State -->
            <div class="bg-white/80 dark:bg-zinc-900/80 border border-zinc-200/50 dark:border-zinc-800/50 backdrop-blur-md rounded-3xl p-12 shadow-xl text-center">
                <div class="w-20 h-20 mx-auto mb-6 bg-indigo-500/10 dark:bg-indigo-500/20 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-zinc-800 dark:text-zinc-100 mb-2">Belum Ada Roadmap</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6 max-w-sm mx-auto">Kerjakan Diagnostic Test terlebih dahulu agar sistem kami dapat menyesuaikan jalur pembelajaran yang optimal untukmu.</p>
                <a href="{{ route('diagnostic') }}" wire:navigate
                   class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-indigo-500 to-violet-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-500/20 hover:scale-[1.02] active:scale-[0.98] transition-all duration-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Mulai Diagnostic Test
                </a>
            </div>
        @else
            <!-- Active Path Header Card -->
            @if ($pathLabel)
                <div class="bg-white/80 dark:bg-zinc-900/80 border border-zinc-200/50 dark:border-zinc-800/50 backdrop-blur-md rounded-3xl p-6 shadow-xl mb-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/20">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 uppercase font-bold tracking-widest">Jalur Pembelajaran Aktif</p>
                                <h2 class="text-lg font-extrabold text-zinc-800 dark:text-zinc-100">{{ $pathLabel }}</h2>
                                @if ($pathCondition)
                                    <p class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5 font-mono">{{ $pathCondition }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Progress Ring -->
                        <div class="flex items-center gap-4">
                            <div class="relative w-16 h-16">
                                <svg class="w-16 h-16 -rotate-90" viewBox="0 0 36 36">
                                    <circle cx="18" cy="18" r="15.5" fill="none" stroke="currentColor" stroke-width="2.5" class="text-zinc-200 dark:text-zinc-800"></circle>
                                    <circle cx="18" cy="18" r="15.5" fill="none" stroke="url(#progressGradient)" stroke-width="2.5"
                                            stroke-dasharray="{{ $progressPercent * 0.974 }} 100"
                                            stroke-linecap="round"
                                            class="transition-all duration-1000 ease-out"></circle>
                                    <defs>
                                        <linearGradient id="progressGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#6366f1"/>
                                            <stop offset="100%" stop-color="#8b5cf6"/>
                                        </linearGradient>
                                    </defs>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-sm font-black text-zinc-800 dark:text-zinc-100">{{ $progressPercent }}%</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold text-zinc-800 dark:text-zinc-100">{{ $completedCount }}/{{ $totalCount }}</p>
                                <p class="text-[10px] text-zinc-500">Modul Selesai</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Timeline Modules -->
            <div class="relative">
                @foreach ($modules as $index => $module)
                    @php
                        $isCompleted = $module['status'] === 'Completed';
                        $isInProgress = $module['status'] === 'In Progress';
                        $isLocked = $module['status'] === 'Locked';
                        $isLast = $index === count($modules) - 1;
                    @endphp

                    <div class="relative flex gap-6 {{ !$isLast ? 'pb-8' : '' }}">
                        <!-- Timeline Node & Connector -->
                        <div class="flex flex-col items-center">
                            <!-- Node Circle -->
                            <div class="relative z-10 w-12 h-12 rounded-2xl flex items-center justify-center transition-all duration-500 flex-shrink-0
                                {{ $isCompleted ? 'bg-gradient-to-br from-emerald-500 to-teal-500 shadow-lg shadow-emerald-500/20' : '' }}
                                {{ $isInProgress ? 'bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 ring-4 ring-indigo-500/20' : '' }}
                                {{ $isLocked ? 'bg-zinc-100 dark:bg-zinc-800' : '' }}
                            ">
                                @if ($isCompleted)
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                @elseif ($isInProgress)
                                    <svg class="w-6 h-6 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                @endif
                            </div>

                            <!-- Connector Line -->
                            @if (!$isLast)
                                <div class="w-0.5 flex-1 mt-2 rounded-full transition-colors duration-500
                                    {{ $isCompleted ? 'bg-gradient-to-b from-emerald-500 to-emerald-300' : 'bg-zinc-200 dark:bg-zinc-800' }}
                                "></div>
                            @endif
                        </div>

                        <!-- Module Card -->
                        <div class="flex-1 min-w-0 group">
                            @if ($isLocked)
                                {{-- Locked card: dimmed, no hover effects --}}
                                <div class="bg-zinc-50/60 dark:bg-zinc-950/30 border border-zinc-200/30 dark:border-zinc-800/30 rounded-2xl p-5 opacity-50 cursor-not-allowed">
                                    <div class="flex items-center justify-between mb-1">
                                        <h3 class="text-sm font-bold text-zinc-400 dark:text-zinc-600 truncate">{{ $module['title'] }}</h3>
                                        <span class="flex-shrink-0 inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-lg bg-zinc-200/80 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-600">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                            Terkunci
                                        </span>
                                    </div>
                                    @if ($module['level'])
                                        <span class="text-[10px] text-zinc-400 dark:text-zinc-600 capitalize font-semibold">Level: {{ $module['level'] }}</span>
                                    @endif
                                    @if ($module['description'])
                                        <p class="text-xs text-zinc-400 dark:text-zinc-600 mt-2 line-clamp-2 leading-relaxed">{{ $module['description'] }}</p>
                                    @endif
                                    <p class="text-[10px] text-zinc-400 dark:text-zinc-600 mt-3 italic">Selesaikan modul sebelumnya untuk membuka kunci.</p>
                                </div>
                            @elseif ($isCompleted)
                                {{-- Completed card: green accents --}}
                                <div class="bg-white/80 dark:bg-zinc-900/80 border border-emerald-200/50 dark:border-emerald-800/30 backdrop-blur-md rounded-2xl p-5 shadow-md hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 group-hover:border-emerald-300 dark:group-hover:border-emerald-700/50">
                                    <div class="flex items-center justify-between mb-1">
                                        <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-100 truncate">{{ $module['title'] }}</h3>
                                        <span class="flex-shrink-0 inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-lg bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            Selesai
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        @if ($module['level'])
                                            <span class="text-[10px] text-zinc-500 dark:text-zinc-400 capitalize font-semibold">Level: {{ $module['level'] }}</span>
                                        @endif
                                        @if ($module['score'])
                                            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">• Skor: {{ $module['score'] }}%</span>
                                        @endif
                                    </div>
                                    @if ($module['description'])
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2 line-clamp-2 leading-relaxed">{{ $module['description'] }}</p>
                                    @endif
                                    <div class="mt-4">
                                        <a href="{{ route('module-detail', $module['id']) }}" wire:navigate
                                           class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-300 transition-colors">
                                            Lihat Detail
                                            <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                        </a>
                                    </div>
                                </div>
                            @else
                                {{-- In Progress card: indigo accents, prominent CTA --}}
                                <div class="bg-white/80 dark:bg-zinc-900/80 border border-indigo-200/50 dark:border-indigo-800/30 backdrop-blur-md rounded-2xl p-5 shadow-md hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 group-hover:border-indigo-300 dark:group-hover:border-indigo-700/50 ring-1 ring-indigo-500/10">
                                    <div class="flex items-center justify-between mb-1">
                                        <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-100 truncate">{{ $module['title'] }}</h3>
                                        <span class="flex-shrink-0 inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-lg bg-indigo-500/10 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400 animate-pulse">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            Sedang Dikerjakan
                                        </span>
                                    </div>
                                    @if ($module['level'])
                                        <span class="text-[10px] text-zinc-500 dark:text-zinc-400 capitalize font-semibold">Level: {{ $module['level'] }}</span>
                                    @endif
                                    @if ($module['description'])
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2 line-clamp-2 leading-relaxed">{{ $module['description'] }}</p>
                                    @endif
                                    <div class="mt-4">
                                        <a href="{{ route('module-detail', $module['id']) }}" wire:navigate
                                           class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-indigo-500 to-violet-600 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-500/20 hover:scale-[1.02] active:scale-[0.98] transition-all duration-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                            Lanjutkan Belajar
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
