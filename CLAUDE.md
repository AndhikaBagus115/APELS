# CLAUDE.md — APELS Project Guide
## Adaptive Professional English Learning System

> Dokumen panduan lengkap pengembangan APELS menggunakan Laravel 12 + Livewire 3.
> Dibuat berdasarkan dokumen spesifikasi resmi + hasil konfirmasi dengan pemilik project.
> Gunakan sebagai referensi utama dari awal development hingga deployment.

---

## KONFIRMASI PROJECT (Final)

| Parameter | Keputusan |
|---|---|
| Pengguna | Mahasiswa aktif (bukan hanya penelitian) |
| Jurusan | Tidak spesifik — umum |
| Deadline MVP | Awal Juli 2025 |
| Jumlah Mahasiswa | 100+ mahasiswa |
| Privasi Data | Self-host — data TIDAK boleh keluar ke server luar |
| Budget API | Ada budget (OpenAI untuk STT/NLP) |
| Server | VPS sendiri (sewa) |
| Fitur Speaking | WAJIB ada di MVP awal |
| Skill Diukur | 3 skill: Speaking, Grammar, Vocabulary |
| Threshold Skor | Sesuai dokumen PDF (hardcoded di engine) |
| Frekuensi Tes | Maksimal 1x per hari per mahasiswa |
| Input Konten | Dosen (via admin panel) |
| Bank Soal | Sudah tersedia (tinggal diimport) |
| Modul Wajib MVP | Financial Presentation + Client Meeting |
| Role Pengguna | 3 role: Admin, Dosen, Mahasiswa |
| Dashboard Dosen | Laporan berkala (bukan real-time) |
| Registrasi | Mahasiswa daftar sendiri |
| Desain UI | Simple, user-friendly, interaktif |
| Platform | Desktop/laptop (tidak perlu mobile responsive) |
| Format Feedback | Teks saja |

---

## DAFTAR ISI

1. [Gambaran Sistem](#1-gambaran-sistem)
2. [Tech Stack](#2-tech-stack)
3. [Setup Project](#3-setup-project)
4. [Struktur Folder](#4-struktur-folder)
5. [Database Schema](#5-database-schema)
6. [Role & Permission](#6-role--permission)
7. [Core System Flow](#7-core-system-flow)
8. [Backend Architecture](#8-backend-architecture)
9. [Diagnostic Engine](#9-diagnostic-engine)
10. [Adaptive Engine](#10-adaptive-engine)
11. [Feedback Engine](#11-feedback-engine)
12. [API Eksternal & Efisiensi](#12-api-eksternal--efisiensi)
13. [Frontend & UI](#13-frontend--ui)
14. [Admin Panel (Filament)](#14-admin-panel-filament)
15. [Security](#15-security)
16. [Standarisasi Kode](#16-standarisasi-kode)
17. [Testing](#17-testing)
18. [Development Priority & Timeline](#18-development-priority--timeline)
19. [Deployment & VPS](#19-deployment--vps)

---

## 1. GAMBARAN SISTEM

### Konsep Utama
APELS bukan LMS biasa. Fokus utama adalah **"Learning Brain"** — sistem yang mampu berpikir dan mengambil keputusan pembelajaran secara otomatis berdasarkan hasil tes mahasiswa.

> ⚠️ **PRINSIP UTAMA:** Yang dibangun adalah "OTAK sistem", bukan "TUBUH sistem".
> Selama adaptive engine berjalan dengan benar, sistem sudah memenuhi tujuan.

### Tiga Skill yang Diukur (Final)
```
1. SPEAKING    → Input audio → OpenAI Whisper (self-hosted atau API)
2. GRAMMAR     → Input teks  → OpenAI GPT-4o Mini
3. VOCABULARY  → Input teks  → OpenAI GPT-4o Mini
```

### Modul Wajib MVP (Final)
```
1. Financial Presentation  → Simulasi presentasi laporan keuangan
2. Client Meeting          → Simulasi komunikasi dengan klien
```

### Alur End-to-End
```
Mahasiswa Login
    ↓
Diagnostic Test (speaking audio + grammar & vocab teks)
    ↓
Backend → OpenAI Whisper (speaking) + GPT-4o Mini (grammar & vocab)
    ↓
ScoringService → Hitung & normalisasi skor (0-100)
    ↓
AdaptiveEngineService → Rule-based engine → Tentukan learning path
    ↓
FeedbackService → Generate feedback teks otomatis
    ↓
Dashboard:
    ├── Radar Chart (3 skill)
    ├── Feedback teks
    └── Learning Roadmap (Financial Presentation + Client Meeting)
```

---

## 2. TECH STACK

```
Backend         : Laravel 12
Reaktif UI      : Livewire 3 (Starter Kit)
UI Components   : Flux UI (bawaan Livewire Starter Kit)
Styling         : TailwindCSS
JS Ringan       : Alpine.js
Chart           : ApexCharts
Admin Panel     : Filament 3
Database        : MySQL 8+
Queue           : Laravel Queue (database driver dev → Redis production)
Cache           : Laravel Cache (file dev → Redis production)
Auth            : Laravel built-in (Livewire Starter Kit)
PHP             : 8.2 - 8.4
Server          : VPS Ubuntu 22.04 LTS
```

### API Eksternal (OpenAI — ada budget)
```
Speaking STT    : OpenAI Whisper API  → $0.003/menit audio
Grammar & Vocab : OpenAI GPT-4o Mini → $0.15/1M input token
Estimasi biaya  : ~$6-12/semester untuk 100+ mahasiswa
```

> ✅ Karena ada budget dan data hanya berupa audio/teks tes (bukan data sensitif seperti KTP/nilai akademik),
> OpenAI API dapat digunakan. Data yang dikirim ke OpenAI hanya berupa rekaman jawaban tes, bukan identitas pribadi mahasiswa.

---

## 3. SETUP PROJECT

### 3.1 Instalasi Awal
```bash
# Buat project Laravel 12 dengan Livewire Starter Kit
laravel new apels --livewire
cd apels

# Install package tambahan
composer require filament/filament:"^3.0" -W
composer require spatie/laravel-permission       # role & permission
composer require openai-php/laravel              # OpenAI client

# Install NPM dependencies
npm install apexcharts
npm run build
```

### 3.2 Environment (.env)
```env
APP_NAME=APELS
APP_ENV=local
APP_KEY=                          # php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=apels_db
DB_USERNAME=root
DB_PASSWORD=

# Queue
QUEUE_CONNECTION=database         # ganti redis di production

# Cache
CACHE_DRIVER=file                 # ganti redis di production

# OpenAI
OPENAI_API_KEY=sk-...             # isi dengan API key OpenAI

# APELS Config
APELS_DAILY_TEST_LIMIT=1          # maks 1 tes per hari per mahasiswa
APELS_AUDIO_MAX_MB=10             # maks ukuran audio 10MB
APELS_STT_TIMEOUT=60              # timeout STT API (detik)
APELS_NLP_TIMEOUT=30              # timeout NLP API (detik)

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

### 3.3 Setup Database & Filament
```bash
# Buat database
mysql -u root -p -e "CREATE DATABASE apels_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Setup queue table
php artisan queue:table

# Jalankan migrasi
php artisan migrate

# Setup Filament admin panel
php artisan filament:install --panels

# Setup Spatie Permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate

# Buat admin user pertama
php artisan make:filament-user
```

### 3.4 Setup OpenAI
```bash
# Publish config openai-php/laravel
php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"
```

---

## 4. STRUKTUR FOLDER

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── DiagnosticController.php
│   │   ├── DashboardController.php
│   │   ├── LearningPathController.php
│   │   └── ReportController.php
│   ├── Middleware/
│   │   └── EnsureDailyTestLimit.php      ← Cek maks 1 tes per hari
│   └── Requests/
│       └── SubmitDiagnosticRequest.php
│
├── Livewire/
│   ├── Student/
│   │   ├── DiagnosticTest.php            ← Wizard 3 step: speaking, grammar, vocab
│   │   ├── Dashboard.php                 ← Radar chart + roadmap + feedback
│   │   ├── LearningPath.php              ← Roadmap modul
│   │   └── Exercises.php                 ← Latihan soal per modul
│   └── Lecturer/
│       └── Reports.php                   ← Laporan berkala mahasiswa
│
├── Services/
│   ├── DiagnosticService.php             ← Orkestrasi proses tes
│   ├── ScoringService.php                ← Hitung & normalisasi skor
│   ├── AdaptiveEngineService.php         ← CORE: rule-based engine (PRIORITAS UTAMA)
│   ├── FeedbackService.php               ← Generate feedback teks otomatis
│   ├── SpeechToTextService.php           ← Wrapper OpenAI Whisper API
│   └── NlpAnalysisService.php            ← Wrapper OpenAI GPT-4o Mini
│
├── Jobs/
│   ├── ProcessSpeakingAudio.php          ← Async: kirim audio ke Whisper
│   └── ReEvaluateLearningPath.php        ← Async: re-evaluasi path
│
├── Models/
│   ├── User.php
│   ├── DiagnosticResult.php
│   ├── UserLearningPath.php
│   ├── Module.php
│   ├── UserModuleProgress.php
│   ├── Question.php
│   └── Feedback.php
│
└── Filament/
    └── Resources/
        ├── UserResource.php
        ├── QuestionResource.php          ← Import bank soal dari dosen
        ├── ModuleResource.php
        └── DiagnosticResultResource.php  ← Laporan untuk dosen

config/
├── apels.php                             ← Konfigurasi global
└── openai.php                            ← Auto-publish dari openai-php/laravel

database/
├── migrations/
└── seeders/
    ├── RoleSeeder.php                    ← Seed 3 role: admin, dosen, mahasiswa
    ├── LearningPathSeeder.php
    └── ModuleSeeder.php                  ← Seed modul wajib MVP
```

---

## 5. DATABASE SCHEMA

### Migrasi Lengkap

```php
// Tambahan kolom ke tabel users
Schema::table('users', function (Blueprint $table) {
    $table->string('nim')->nullable()->unique();
    $table->integer('level')->default(1);
    $table->string('avatar')->nullable();
    // role dihandle oleh spatie/laravel-permission
});

// diagnostic_results
Schema::create('diagnostic_results', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('speaking')->default(0);        // skor 0-100
    $table->integer('grammar')->default(0);         // skor 0-100
    $table->integer('vocabulary')->default(0);      // skor 0-100
    $table->float('overall')->default(0);           // weighted average
    $table->integer('attempt')->default(1);         // ke berapa kali tes
    $table->string('audio_path')->nullable();       // path file audio speaking
    $table->boolean('is_speaking_processed')->default(false); // status queue
    $table->timestamps();
});

// user_learning_paths
Schema::create('user_learning_paths', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('path_key');
    $table->enum('status', ['active', 'completed'])->default('active');
    $table->timestamp('assigned_at')->useCurrent();
    $table->timestamps();
});

// modules
Schema::create('modules', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('path_key');
    $table->enum('level', ['basic', 'intermediate', 'advanced', 'professional']);
    $table->string('tag');                          // 'basic' | 'advanced'
    $table->integer('order_index')->default(0);
    $table->json('content')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// user_module_progress
Schema::create('user_module_progress', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('module_id')->constrained()->cascadeOnDelete();
    $table->boolean('is_unlocked')->default(false);
    $table->boolean('is_completed')->default(false);
    $table->integer('score')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'module_id']);
});

// questions (bank soal dari dosen)
Schema::create('questions', function (Blueprint $table) {
    $table->id();
    $table->text('question');
    $table->string('option_a');
    $table->string('option_b');
    $table->string('option_c');
    $table->string('option_d');
    $table->enum('correct_answer', ['a', 'b', 'c', 'd']);
    $table->string('tag');                          // 'basic' | 'intermediate' | 'advanced'
    $table->enum('type', ['grammar', 'vocabulary']);
    $table->integer('difficulty')->default(1);      // 1-5
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// feedbacks
Schema::create('feedbacks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('diagnostic_result_id')->constrained()->cascadeOnDelete();
    $table->text('message');                        // feedback teks final
    $table->json('details')->nullable();            // detail per skill
    $table->string('next_focus')->nullable();       // rekomendasi fokus berikutnya
    $table->timestamps();
});
```

---

## 6. ROLE & PERMISSION

### 3 Role (Final)
```
admin      → Full akses: kelola semua data, user, soal, modul
dosen      → Akses: input soal, lihat laporan berkala mahasiswa
mahasiswa  → Akses: tes diagnosis, dashboard, learning path, exercises
```

### RoleSeeder
```php
// database/seeders/RoleSeeder.php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Buat roles
        $admin     = Role::create(['name' => 'admin']);
        $dosen     = Role::create(['name' => 'dosen']);
        $mahasiswa = Role::create(['name' => 'mahasiswa']);

        // Permission mahasiswa
        Permission::create(['name' => 'take diagnostic']);
        Permission::create(['name' => 'view dashboard']);
        Permission::create(['name' => 'view learning path']);
        Permission::create(['name' => 'do exercises']);

        // Permission dosen
        Permission::create(['name' => 'manage questions']);
        Permission::create(['name' => 'view reports']);
        Permission::create(['name' => 'import questions']);

        // Permission admin
        Permission::create(['name' => 'manage users']);
        Permission::create(['name' => 'manage modules']);
        Permission::create(['name' => 'manage all']);

        // Assign permissions ke role
        $mahasiswa->givePermissionTo(['take diagnostic', 'view dashboard', 'view learning path', 'do exercises']);
        $dosen->givePermissionTo(['manage questions', 'view reports', 'import questions']);
        $admin->givePermissionTo(Permission::all());
    }
}
```

### Middleware Role di Route
```php
// routes/web.php
Route::middleware(['auth', 'role:mahasiswa'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/learning-path', LearningPath::class)->name('learning-path');
    Route::get('/exercises', Exercises::class)->name('exercises');
    Route::get('/diagnostic', DiagnosticTest::class)->name('diagnostic');
});

Route::middleware(['auth', 'role:dosen'])->group(function () {
    Route::get('/reports', Reports::class)->name('reports');
});

// Admin → Filament panel (route otomatis dari Filament)
```

---

## 7. CORE SYSTEM FLOW

### Endpoint Struktur
```
POST   /api/diagnostic/submit      → Submit hasil tes (text + audio)
GET    /api/dashboard              → Data dashboard mahasiswa
GET    /api/learning-path          → Learning path & status modul
GET    /api/feedback/latest        → Feedback teks terbaru
GET    /api/reports/summary        → Laporan berkala (dosen)
```

### Standar API Response
```json
// Success
{
    "status": "success",
    "message": "Tes berhasil diproses",
    "data": { ... }
}

// Error
{
    "status": "error",
    "message": "Deskripsi error",
    "errors": { ... },
    "code": 422
}
```

---

## 8. BACKEND ARCHITECTURE

### Service Pattern (WAJIB)
Semua business logic **HARUS** di Service, bukan di Controller.

```php
// ✅ BENAR
class DiagnosticController extends Controller
{
    public function submit(SubmitDiagnosticRequest $request, DiagnosticService $service)
    {
        $result = $service->process(auth()->id(), $request->validated());
        return response()->json(['status' => 'success', 'data' => $result]);
    }
}
```

### DiagnosticService (Orkestrasi)
```php
<?php
namespace App\Services;

class DiagnosticService
{
    public function __construct(
        private SpeechToTextService   $sttService,
        private NlpAnalysisService    $nlpService,
        private ScoringService        $scoringService,
        private AdaptiveEngineService $adaptiveEngine,
        private FeedbackService       $feedbackService,
    ) {}

    public function process(int $userId, array $input): array
    {
        // 1. Proses grammar & vocabulary via NLP (sinkron)
        $nlpResult = $this->nlpService->analyze($input['text_answers']);

        // 2. Simpan hasil awal (speaking akan diisi via queue)
        $diagnostic = DiagnosticResult::create([
            'user_id'    => $userId,
            'speaking'   => 0,                      // sementara 0, diisi queue
            'grammar'    => $nlpResult['grammar_score'],
            'vocabulary' => $nlpResult['vocab_score'],
            'overall'    => 0,
            'attempt'    => $this->getAttemptNumber($userId),
            'audio_path' => $input['audio_path'] ?? null,
            'is_speaking_processed' => false,
        ]);

        // 3. Dispatch job speaking ke queue (async)
        if (isset($input['audio_path'])) {
            ProcessSpeakingAudio::dispatch($userId, $input['audio_path'], $diagnostic->id);
        } else {
            // Jika tidak ada audio, jalankan engine langsung
            $this->runEngineAndFeedback($userId, $diagnostic);
        }

        return [
            'diagnostic_id' => $diagnostic->id,
            'message'       => 'Tes berhasil dikirim. Hasil speaking sedang diproses.',
            'grammar'       => $nlpResult['grammar_score'],
            'vocabulary'    => $nlpResult['vocab_score'],
        ];
    }

    public function runEngineAndFeedback(int $userId, DiagnosticResult $diagnostic): void
    {
        // Hitung overall setelah semua skor tersedia
        $scores = [
            'speaking'   => $diagnostic->speaking,
            'grammar'    => $diagnostic->grammar,
            'vocabulary' => $diagnostic->vocabulary,
        ];

        $overall = $this->scoringService->calculateOverall($scores);
        $diagnostic->update(['overall' => $overall]);

        // Jalankan adaptive engine
        $pathResult = $this->adaptiveEngine->run($userId, $scores);

        // Generate feedback teks
        $feedback = $this->feedbackService->generate($scores, $pathResult['learning_path']);

        // Simpan feedback
        Feedback::create([
            'user_id'              => $userId,
            'diagnostic_result_id' => $diagnostic->id,
            'message'              => $feedback['message'],
            'details'              => $feedback['details'],
            'next_focus'           => $feedback['next_focus'],
        ]);
    }

    private function getAttemptNumber(int $userId): int
    {
        return DiagnosticResult::where('user_id', $userId)->count() + 1;
    }
}
```

### ScoringService
```php
<?php
namespace App\Services;

class ScoringService
{
    // Bobot 3 skill (total harus = 1.0)
    private array $weights = [
        'speaking'   => 0.40,
        'grammar'    => 0.30,
        'vocabulary' => 0.30,
    ];

    public function calculateOverall(array $scores): float
    {
        $total = 0;
        foreach ($this->weights as $skill => $weight) {
            $total += ($scores[$skill] ?? 0) * $weight;
        }
        return round($total, 2);
    }

    // Normalisasi skor dari API (0.0 - 1.0) ke (0 - 100)
    public function normalize(float $score, float $min = 0.0, float $max = 1.0): int
    {
        return (int) round((($score - $min) / ($max - $min)) * 100);
    }
}
```

---

## 9. DIAGNOSTIC ENGINE

### Speaking — OpenAI Whisper API
```php
<?php
namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class SpeechToTextService
{
    public function analyze(string $audioPath): int
    {
        try {
            $response = OpenAI::audio()->transcribe([
                'model'    => 'whisper-1',
                'file'     => fopen($audioPath, 'r'),
                'language' => 'en',
            ]);

            $transcript = $response->text;
            return $this->scoreFromTranscript($transcript);

        } catch (\Exception $e) {
            Log::error('Whisper API Error: ' . $e->getMessage());
            return 0; // fallback score jika API gagal
        }
    }

    private function scoreFromTranscript(string $transcript): int
    {
        $wordCount = str_word_count($transcript);

        // Hitung skor dari kelengkapan dan panjang jawaban
        // Minimal 20 kata untuk skor penuh
        $lengthScore = min(($wordCount / 20) * 100, 100);

        // Penalti jika terlalu pendek (< 5 kata)
        if ($wordCount < 5) return max((int) $lengthScore, 10);

        return (int) round($lengthScore);
    }
}
```

### Grammar & Vocabulary — OpenAI GPT-4o Mini
```php
<?php
namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class NlpAnalysisService
{
    public function analyze(array $textAnswers): array
    {
        try {
            $combinedText = implode(' ', $textAnswers);

            $response = OpenAI::chat()->create([
                'model'   => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'You are an English language evaluator. Analyze the given text and return ONLY a JSON object with these exact keys: grammar_score (integer 0-100), vocab_score (integer 0-100). No explanation, no markdown, just the JSON object.'
                    ],
                    [
                        'role'    => 'user',
                        'content' => $combinedText
                    ]
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 100,
            ]);

            $result = json_decode($response->choices[0]->message->content, true);

            return [
                'grammar_score' => (int) ($result['grammar_score'] ?? 0),
                'vocab_score'   => (int) ($result['vocab_score'] ?? 0),
            ];

        } catch (\Exception $e) {
            Log::error('GPT-4o Mini NLP Error: ' . $e->getMessage());
            return ['grammar_score' => 0, 'vocab_score' => 0];
        }
    }
}
```

### Middleware: Limit 1 Tes Per Hari
```php
// app/Http/Middleware/EnsureDailyTestLimit.php
class EnsureDailyTestLimit
{
    public function handle(Request $request, Closure $next)
    {
        $userId    = auth()->id();
        $todayTest = DiagnosticResult::where('user_id', $userId)
                        ->whereDate('created_at', today())
                        ->exists();

        if ($todayTest) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda sudah mengerjakan tes hari ini. Coba lagi besok.',
                'code'    => 429
            ], 429);
        }

        return $next($request);
    }
}
```

---

## 10. ADAPTIVE ENGINE

> ⚠️ **INI ADALAH PRIORITAS UTAMA. HARUS STABIL SEBELUM FITUR LAIN DIKEMBANGKAN.**

### Threshold Skor (Sesuai Dokumen PDF)
```
< 60  = Rendah  → jalur dasar
60-70 = Sedang  → jalur intermediate
> 70  = Tinggi  → jalur profesional/advanced
```

### Learning Path Master (3 Skill: Speaking, Grammar, Vocabulary)
```php
// config/learning_paths.php
return [

    // CRITICAL — semua skill rendah
    'emergency_foundation' => [
        'label'     => 'Foundation Level',
        'color'     => 'red',
        'condition' => 'Semua skor < 50',
        'modules'   => ['basic_english', 'simple_grammar', 'daily_vocabulary'],
    ],

    // MULTI-SKILL RENDAH
    'fundamental_communication' => [
        'label'     => 'Communication Fundamentals',
        'color'     => 'orange',
        'condition' => 'Speaking < 60 DAN Vocabulary < 60',
        'modules'   => ['pronunciation', 'basic_speaking', 'vocab_daily'],
    ],

    // SINGLE SKILL RENDAH
    'basic_speaking' => [
        'label'     => 'Speaking Starter',
        'color'     => 'yellow',
        'condition' => 'Speaking < 60',
        'modules'   => ['pronunciation', 'fluency_basic'],
    ],
    'grammar_foundation' => [
        'label'     => 'Grammar Builder',
        'color'     => 'blue',
        'condition' => 'Grammar < 60',
        'modules'   => ['basic_tenses', 'sentence_structure'],
    ],
    'vocabulary_builder' => [
        'label'     => 'Vocabulary Builder',
        'color'     => 'cyan',
        'condition' => 'Vocabulary < 60',
        'modules'   => ['vocab_daily', 'accounting_terms'],
    ],

    // INTERMEDIATE
    'intermediate_path' => [
        'label'     => 'Intermediate English',
        'color'     => 'indigo',
        'condition' => 'Semua skor 60-70',
        'modules'   => ['business_writing', 'presentation_skills'],
    ],

    // PROFESSIONAL (Modul MVP wajib ada di sini)
    'professional_simulation' => [
        'label'     => 'Professional Simulation',
        'color'     => 'purple',
        'condition' => 'Speaking >= 70 DAN Grammar >= 70',
        'modules'   => ['financial_presentation', 'client_meeting'], // MVP wajib
    ],

    // INDUSTRY READY
    'industry_ready' => [
        'label'     => 'Industry Ready',
        'color'     => 'green',
        'condition' => 'Speaking >= 80 DAN Semua >= 75',
        'modules'   => ['financial_presentation', 'client_meeting', 'advanced_negotiation'],
    ],
];
```

### AdaptiveEngineService (Core)
```php
<?php
namespace App\Services;

use App\Models\Module;
use App\Models\UserLearningPath;
use App\Models\UserModuleProgress;

class AdaptiveEngineService
{
    public function run(int $userId, array $scores): array
    {
        $pathKey = $this->evaluateRules($scores);
        $modules = $this->resolveModules($pathKey, $scores);
        $this->persist($userId, $pathKey, $modules);

        return [
            'learning_path'    => $pathKey,
            'path_label'       => config("learning_paths.{$pathKey}.label"),
            'modules_unlocked' => $modules['unlocked'],
            'modules_locked'   => $modules['locked'],
        ];
    }

    /**
     * RULE EVALUATION
     * Urutan: paling spesifik → paling umum
     * Threshold sesuai dokumen PDF: < 60 rendah, 60-70 sedang, > 70 tinggi
     */
    private function evaluateRules(array $s): string
    {
        $sp = $s['speaking'];
        $gr = $s['grammar'];
        $vo = $s['vocabulary'];

        // CRITICAL: semua rendah
        if ($sp < 50 && $gr < 50 && $vo < 50) {
            return 'emergency_foundation';
        }

        // MULTI-SKILL RENDAH
        if ($sp < 60 && $vo < 60) return 'fundamental_communication';

        // SINGLE SKILL RENDAH
        if ($sp < 60) return 'basic_speaking';
        if ($gr < 60) return 'grammar_foundation';
        if ($vo < 60) return 'vocabulary_builder';

        // INDUSTRY READY
        if ($sp >= 80 && $gr >= 75 && $vo >= 75) return 'industry_ready';

        // PROFESSIONAL
        if ($sp >= 70 && $gr >= 70) return 'professional_simulation';

        // DEFAULT: INTERMEDIATE
        return 'intermediate_path';
    }

    private function resolveModules(string $pathKey, array $scores): array
    {
        $allModules = Module::where('is_active', true)->get();
        $unlocked = [];
        $locked   = [];

        foreach ($allModules as $module) {
            if ($this->shouldUnlock($module, $pathKey, $scores)) {
                $unlocked[] = $module->id;
            } else {
                $locked[] = $module->id;
            }
        }

        return compact('unlocked', 'locked');
    }

    private function shouldUnlock($module, string $pathKey, array $scores): bool
    {
        // Modul milik path aktif → unlock
        if ($module->path_key === $pathKey) return true;

        // Level basic → selalu unlock
        if ($module->level === 'basic') return true;

        // Modul professional (financial presentation, client meeting)
        // Hanya unlock jika semua skor >= 70
        if ($module->level === 'professional') {
            return $scores['speaking'] >= 70
                && $scores['grammar'] >= 70
                && $scores['vocabulary'] >= 70;
        }

        return false;
    }

    private function persist(int $userId, string $pathKey, array $modules): void
    {
        UserLearningPath::updateOrCreate(
            ['user_id' => $userId],
            ['path_key' => $pathKey, 'status' => 'active', 'assigned_at' => now()]
        );

        foreach ($modules['unlocked'] as $moduleId) {
            UserModuleProgress::updateOrCreate(
                ['user_id' => $userId, 'module_id' => $moduleId],
                ['is_unlocked' => true]
            );
        }

        foreach ($modules['locked'] as $moduleId) {
            UserModuleProgress::updateOrCreate(
                ['user_id' => $userId, 'module_id' => $moduleId],
                ['is_unlocked' => false]
            );
        }
    }
}
```

### Re-evaluation (1x per hari)
```php
// app/Services/ReEvaluationService.php
class ReEvaluationService
{
    public function canTakeTestToday(int $userId): bool
    {
        return !DiagnosticResult::where('user_id', $userId)
                    ->whereDate('created_at', today())
                    ->exists();
    }

    public function shouldReEvaluateByProgress(int $userId): bool
    {
        $total     = UserModuleProgress::where('user_id', $userId)->count();
        $completed = UserModuleProgress::where('user_id', $userId)
                        ->where('is_completed', true)->count();

        if ($total === 0) return false;
        return ($completed / $total) >= 0.7; // 70% modul selesai → sarankan tes ulang
    }
}
```

---

## 11. FEEDBACK ENGINE

Format feedback: **teks saja** (sesuai konfirmasi).

```php
<?php
namespace App\Services;

class FeedbackService
{
    // Template teks berdasarkan kondisi skor
    private array $templates = [
        'speaking_low'   => 'Kemampuan speaking Anda perlu ditingkatkan. Fokus pada pronunciation dan kelancaran berbicara.',
        'speaking_mid'   => 'Speaking Anda sudah cukup baik. Tingkatkan kelancaran untuk percakapan profesional.',
        'speaking_high'  => 'Speaking Anda sangat baik! Siap untuk simulasi komunikasi profesional.',
        'grammar_low'    => 'Grammar Anda masih perlu diperkuat. Mulai dari basic tenses dan sentence structure.',
        'grammar_mid'    => 'Grammar Anda berkembang dengan baik. Lanjutkan ke business writing.',
        'grammar_high'   => 'Grammar Anda sudah kuat.',
        'vocab_low'      => 'Perbanyak kosakata akuntansi dan bisnis Anda.',
        'vocab_mid'      => 'Kosakata Anda cukup baik. Fokus pada istilah akuntansi yang lebih spesifik.',
        'vocab_high'     => 'Kosakata profesional Anda sangat baik.',
    ];

    private array $pathNextFocus = [
        'emergency_foundation'      => 'penguatan dasar Bahasa Inggris secara menyeluruh',
        'fundamental_communication' => 'speaking dasar dan kosakata harian',
        'basic_speaking'            => 'pronunciation dan kelancaran berbicara',
        'grammar_foundation'        => 'tata bahasa dan struktur kalimat',
        'vocabulary_builder'        => 'kosakata akuntansi profesional',
        'intermediate_path'         => 'business writing dan keterampilan presentasi',
        'professional_simulation'   => 'simulasi Financial Presentation dan Client Meeting',
        'industry_ready'            => 'negosiasi tingkat lanjut dan komunikasi internasional',
    ];

    public function generate(array $scores, string $pathKey): array
    {
        $messages = [];

        // Evaluasi speaking
        $messages[] = match(true) {
            $scores['speaking'] < 60 => $this->templates['speaking_low'],
            $scores['speaking'] < 70 => $this->templates['speaking_mid'],
            default                  => $this->templates['speaking_high'],
        };

        // Evaluasi grammar
        $messages[] = match(true) {
            $scores['grammar'] < 60 => $this->templates['grammar_low'],
            $scores['grammar'] < 70 => $this->templates['grammar_mid'],
            default                 => $this->templates['grammar_high'],
        };

        // Evaluasi vocabulary
        $messages[] = match(true) {
            $scores['vocabulary'] < 60 => $this->templates['vocab_low'],
            $scores['vocabulary'] < 70 => $this->templates['vocab_mid'],
            default                    => $this->templates['vocab_high'],
        };

        // Next focus
        $nextFocus  = $this->pathNextFocus[$pathKey] ?? 'pengembangan skill lebih lanjut';
        $messages[] = "Langkah berikutnya: fokus pada {$nextFocus}.";

        return [
            'message'    => implode(' ', $messages),
            'details'    => $messages,
            'next_focus' => $nextFocus,
        ];
    }
}
```

---

## 12. API EKSTERNAL & EFISIENSI

### Prinsip Efisiensi (WAJIB IKUTI)
```
1. QUEUE   → speaking audio diproses async, tidak blocking user
2. CACHE   → cache hasil NLP per hash teks (TTL 24 jam)
3. RETRY   → max 3x retry jika API gagal, backoff 5 detik
4. TIMEOUT → Whisper 60 detik, GPT-4o Mini 30 detik
3. FALLBACK → jika API gagal, skor = 0 (tidak crash sistem)
4. LOG     → semua error API dicatat di storage/logs/laravel.log
```

### Queue Job — ProcessSpeakingAudio
```php
// app/Jobs/ProcessSpeakingAudio.php
class ProcessSpeakingAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;
    public int $backoff = 5;

    public function __construct(
        private int    $userId,
        private string $audioPath,
        private int    $diagnosticId
    ) {}

    public function handle(SpeechToTextService $stt, DiagnosticService $diagnostic): void
    {
        $score = $stt->analyze($this->audioPath);

        $result = DiagnosticResult::find($this->diagnosticId);
        $result->update([
            'speaking'              => $score,
            'is_speaking_processed' => true,
        ]);

        // Jalankan engine setelah speaking skor tersedia
        $diagnostic->runEngineAndFeedback($this->userId, $result->fresh());

        // Hapus file audio setelah diproses (hemat storage)
        if (file_exists($this->audioPath)) {
            unlink($this->audioPath);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ProcessSpeakingAudio gagal user {$this->userId}: " . $e->getMessage());
        DiagnosticResult::where('id', $this->diagnosticId)->update([
            'speaking'              => 0,
            'is_speaking_processed' => true,
        ]);
    }
}
```

### Caching NLP Result
```php
// Di NlpAnalysisService — cache hasil untuk teks yang sama
public function analyze(array $textAnswers): array
{
    $hash     = md5(implode('|', $textAnswers));
    $cacheKey = "nlp_result_{$hash}";

    return Cache::remember($cacheKey, now()->addHours(24), function () use ($textAnswers) {
        return $this->callGpt4oMini($textAnswers);
    });
}
```

---

## 13. FRONTEND & UI

### Prinsip UI (Sesuai Konfirmasi)
```
✅ Simple dan user-friendly
✅ Interaktif (Livewire + Alpine.js)
✅ Desktop/laptop only (min-width: 1024px)
✅ Tidak ada branding kampus khusus
✅ Feedback hanya teks (tidak perlu grafik feedback)
```

### Livewire Dashboard Component
```php
// app/Livewire/Student/Dashboard.php
class Dashboard extends Component
{
    public array  $skills    = [];
    public string $pathLabel = '';
    public string $pathKey   = '';
    public array  $modules   = [];
    public string $feedback  = '';
    public int    $progress  = 0;
    public bool   $speakingProcessing = false;

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $userId     = auth()->id();
        $diagnostic = DiagnosticResult::where('user_id', $userId)->latest()->first();
        $path       = UserLearningPath::where('user_id', $userId)->first();

        if ($diagnostic) {
            $this->skills = [
                ['subject' => 'Speaking',   'value' => $diagnostic->speaking],
                ['subject' => 'Grammar',    'value' => $diagnostic->grammar],
                ['subject' => 'Vocabulary', 'value' => $diagnostic->vocabulary],
            ];
            $this->speakingProcessing = !$diagnostic->is_speaking_processed;
        }

        if ($path) {
            $this->pathKey   = $path->path_key;
            $this->pathLabel = config("learning_paths.{$path->path_key}.label", '-');
            $this->modules   = $this->getModulesWithStatus($userId);
            $this->progress  = $this->calcProgress($userId);
        }

        $latestFeedback = Feedback::where('user_id', $userId)->latest()->first();
        $this->feedback = $latestFeedback?->message ?? '';
    }

    // Polling tiap 5 detik jika speaking masih diproses
    public function getListeners(): array
    {
        return $this->speakingProcessing ? ['refreshDashboard' => 'loadData'] : [];
    }

    private function calcProgress(int $userId): int
    {
        $total     = UserModuleProgress::where('user_id', $userId)->count();
        $completed = UserModuleProgress::where('user_id', $userId)
                        ->where('is_completed', true)->count();
        return $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    }

    private function getModulesWithStatus(int $userId): array
    {
        return UserModuleProgress::where('user_id', $userId)
            ->with('module')
            ->get()
            ->map(fn($p) => [
                'title'        => $p->module->title,
                'is_unlocked'  => $p->is_unlocked,
                'is_completed' => $p->is_completed,
                'level'        => $p->module->level,
            ])
            ->toArray();
    }

    public function render()
    {
        return view('livewire.student.dashboard')->layout('layouts.app');
    }
}
```

### Radar Chart 3 Skill (Alpine.js + ApexCharts)
```html
<!-- resources/views/livewire/student/dashboard.blade.php (potongan chart) -->
<div
    x-data="{
        chart: null,
        init() {
            this.chart = new ApexCharts(this.$refs.radar, {
                chart: {
                    type: 'radar',
                    height: 320,
                    toolbar: { show: false },
                    animations: { enabled: true, easing: 'easeinout', speed: 800 }
                },
                series: [
                    { name: 'Your Level', data: {{ json_encode(collect($skills)->pluck('value')) }} },
                    { name: 'Target',     data: [80, 80, 80] }
                ],
                labels: {{ json_encode(collect($skills)->pluck('subject')) }},
                colors: ['#6366f1', '#94a3b8'],
                fill:   { opacity: [0.4, 0.1] },
                stroke: { width: [2, 1], dashArray: [0, 5] },
                yaxis:  { show: false, min: 0, max: 100 },
                markers: { size: 4 }
            });
            this.chart.render();
        }
    }"
    wire:ignore
>
    <div x-ref="radar"></div>
</div>

{{-- Notifikasi speaking masih diproses --}}
@if($speakingProcessing)
<div wire:poll.5000ms="loadData"
     class="bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3 text-sm text-yellow-700">
    ⏳ Hasil speaking sedang diproses...
</div>
@endif

{{-- Feedback teks --}}
@if($feedback)
<div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4">
    <p class="text-sm font-medium text-indigo-700 mb-1">AI Insight</p>
    <p class="text-sm text-slate-700 leading-relaxed">{{ $feedback }}</p>
</div>
@endif
```

---

## 14. ADMIN PANEL (Filament)

### Akses per Role
```
admin  → Filament panel penuh (semua resource)
dosen  → Hanya: QuestionResource, ModuleResource, DiagnosticResultResource (read-only)
```

### Filament Panel Config
```php
// app/Providers/Filament/AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login()
        ->authGuard('web')
        ->resources([
            UserResource::class,
            QuestionResource::class,
            ModuleResource::class,
            DiagnosticResultResource::class,
        ]);
}
```

### QuestionResource (Import Bank Soal Dosen)
```php
// app/Filament/Resources/QuestionResource.php
class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;
    protected static ?string $navigationLabel = 'Bank Soal';
    protected static ?string $navigationIcon  = 'heroicon-o-academic-cap';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Textarea::make('question')->label('Pertanyaan')->required()->columnSpanFull(),
            Grid::make(2)->schema([
                Select::make('type')
                    ->options(['grammar' => 'Grammar', 'vocabulary' => 'Vocabulary'])
                    ->required(),
                Select::make('tag')
                    ->options(['basic' => 'Basic', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'])
                    ->required(),
            ]),
            TextInput::make('option_a')->label('Opsi A')->required(),
            TextInput::make('option_b')->label('Opsi B')->required(),
            TextInput::make('option_c')->label('Opsi C')->required(),
            TextInput::make('option_d')->label('Opsi D')->required(),
            Radio::make('correct_answer')
                ->label('Jawaban Benar')
                ->options(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'])
                ->required()
                ->inline(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('question')->limit(60)->searchable(),
            BadgeColumn::make('type')->colors(['primary' => 'grammar', 'success' => 'vocabulary']),
            BadgeColumn::make('tag'),
            IconColumn::make('is_active')->boolean(),
            TextColumn::make('created_at')->date('d M Y')->sortable(),
        ])->filters([
            SelectFilter::make('type')->options(['grammar' => 'Grammar', 'vocabulary' => 'Vocabulary']),
            SelectFilter::make('tag'),
            TernaryFilter::make('is_active')->label('Status Aktif'),
        ])->actions([
            EditAction::make(),
            DeleteAction::make(),
        ])->bulkActions([
            DeleteBulkAction::make(),
        ]);
    }
}
```

### DiagnosticResultResource (Laporan Berkala Dosen)
```php
// Dosen bisa filter per tanggal, lihat skor mahasiswa
// Hanya read-only untuk role dosen
class DiagnosticResultResource extends Resource
{
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool {
        return auth()->user()->hasRole('admin');
    }
    public static function canDelete(Model $record): bool {
        return auth()->user()->hasRole('admin');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('Mahasiswa')->searchable(),
            TextColumn::make('speaking')->sortable(),
            TextColumn::make('grammar')->sortable(),
            TextColumn::make('vocabulary')->sortable(),
            TextColumn::make('overall')->sortable(),
            TextColumn::make('attempt'),
            TextColumn::make('created_at')->label('Tanggal')->date('d M Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('user')->relationship('user', 'name'),
            Filter::make('created_at')
                ->form([DatePicker::make('from'), DatePicker::make('until')])
                ->query(function ($query, array $data) {
                    return $query
                        ->when($data['from'],  fn($q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['until'], fn($q) => $q->whereDate('created_at', '<=', $data['until']));
                }),
        ]);
    }
}
```

---

## 15. SECURITY

### Authentication
```php
// routes/web.php — semua route protected by role
Route::middleware(['auth', 'verified', 'role:mahasiswa'])->group(function () {
    Route::get('/dashboard',     [Dashboard::class,     '__invoke'])->name('dashboard');
    Route::get('/learning-path', [LearningPath::class,  '__invoke'])->name('learning-path');
    Route::get('/exercises',     [Exercises::class,     '__invoke'])->name('exercises');
    Route::get('/diagnostic',    [DiagnosticTest::class,'__invoke'])
         ->middleware('App\Http\Middleware\EnsureDailyTestLimit')
         ->name('diagnostic');
});

Route::middleware(['auth', 'verified', 'role:dosen'])->group(function () {
    Route::get('/reports', [Reports::class, '__invoke'])->name('reports');
});
```

### Form Request Validation
```php
// app/Http/Requests/SubmitDiagnosticRequest.php
class SubmitDiagnosticRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'text_answers'   => ['required', 'array', 'min:3', 'max:20'],
            'text_answers.*' => ['required', 'string', 'min:5', 'max:500'],
            'audio'          => [
                'required',
                'file',
                'mimes:wav,mp3,webm,ogg',
                'max:' . (config('apels.audio_max_mb', 10) * 1024),
            ],
        ];
    }
}
```

### Keamanan API Key
```
✅ API key HANYA di .env — JANGAN hardcode
✅ .env masuk .gitignore — JANGAN commit ke git
✅ Akses key via config() helper, bukan env() langsung di kode
✅ Rotasi API key jika ada dugaan kebocoran
✅ CSRF protection aktif (bawaan Laravel)
✅ Audio file disimpan di storage/private, bukan public
✅ Audio dihapus setelah diproses queue
```

```php
// ✅ BENAR
$key = config('openai.api_key');

// ❌ SALAH
$key = 'sk-proj-xxxxxx';
$key = env('OPENAI_API_KEY'); // jangan panggil env() langsung di service
```

---

## 16. STANDARISASI KODE

### Naming Convention
```
Model           → PascalCase singular    : DiagnosticResult, UserLearningPath
Controller      → PascalCase + Controller: DiagnosticController
Service         → PascalCase + Service   : AdaptiveEngineService
Livewire        → PascalCase             : Dashboard, DiagnosticTest
Job             → PascalCase + verb      : ProcessSpeakingAudio
Migration       → snake_case deskriptif  : create_diagnostic_results_table
Route name      → kebab-case             : learning-path, diagnostic-test
Blade view      → kebab-case             : dashboard.blade.php
Variable PHP    → camelCase              : $pathKey, $userId
Config key      → snake_case             : learning_paths, daily_api_limit
```

### Standar Method Service
```php
// Setiap Service HARUS:
// ✅ Return type declaration
// ✅ Try-catch untuk operasi API/DB
// ✅ Log::error untuk semua exception
// ✅ Fallback value jika gagal

public function process(int $userId, array $data): array  // return type jelas
{
    try {
        $result = $this->doWork($data);
        Log::info("Process berhasil untuk user {$userId}");
        return $result;
    } catch (\Exception $e) {
        Log::error("Process gagal user {$userId}: " . $e->getMessage());
        return $this->fallbackResult(); // tidak crash, return default
    }
}
```

### Standar Blade + Livewire
```html
{{-- ✅ Gunakan wire:loading untuk UX saat proses --}}
<button wire:click="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50">
    <span wire:loading.remove>Mulai Tes</span>
    <span wire:loading>Memproses...</span>
</button>

{{-- ✅ Gunakan wire:ignore untuk elemen JS eksternal --}}
<div wire:ignore>
    <canvas id="skillRadar"></canvas>
</div>

{{-- ✅ Gunakan komponen untuk elemen yang diulang --}}
<x-module-card :module="$module" :status="$status" />
```

---

## 17. TESTING

### Unit Test — Adaptive Engine
```php
// tests/Unit/AdaptiveEngineTest.php
class AdaptiveEngineTest extends TestCase
{
    private AdaptiveEngineService $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new AdaptiveEngineService();
    }

    /** @test */
    public function semua_skor_dibawah_50_menghasilkan_emergency_foundation()
    {
        $scores = ['speaking' => 40, 'grammar' => 45, 'vocabulary' => 35];
        $path = $this->callPrivate('evaluateRules', [$scores]);
        $this->assertEquals('emergency_foundation', $path);
    }

    /** @test */
    public function speaking_grammar_diatas_70_menghasilkan_professional_simulation()
    {
        $scores = ['speaking' => 75, 'grammar' => 72, 'vocabulary' => 65];
        $path = $this->callPrivate('evaluateRules', [$scores]);
        $this->assertEquals('professional_simulation', $path);
    }

    /** @test */
    public function hanya_speaking_rendah_menghasilkan_basic_speaking()
    {
        $scores = ['speaking' => 55, 'grammar' => 70, 'vocabulary' => 68];
        $path = $this->callPrivate('evaluateRules', [$scores]);
        $this->assertEquals('basic_speaking', $path);
    }

    /** @test */
    public function semua_skor_diatas_80_menghasilkan_industry_ready()
    {
        $scores = ['speaking' => 85, 'grammar' => 80, 'vocabulary' => 78];
        $path = $this->callPrivate('evaluateRules', [$scores]);
        $this->assertEquals('industry_ready', $path);
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($this->engine, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($this->engine, $args);
    }
}
```

### Feature Test — Diagnostic Submission
```php
// tests/Feature/DiagnosticTest.php
class DiagnosticTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function mahasiswa_bisa_submit_diagnostic()
    {
        $user = User::factory()->create();
        $user->assignRole('mahasiswa');

        Storage::fake('local');
        $audio = UploadedFile::fake()->create('audio.wav', 100, 'audio/wav');

        $response = $this->actingAs($user)->postJson('/api/diagnostic/submit', [
            'text_answers' => ['The company reports quarterly earnings.', 'Assets equal liabilities plus equity.', 'The audit showed no discrepancies.'],
            'audio'        => $audio,
        ]);

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
        $this->assertDatabaseHas('diagnostic_results', ['user_id' => $user->id]);
    }

    /** @test */
    public function mahasiswa_tidak_bisa_tes_dua_kali_sehari()
    {
        $user = User::factory()->create();
        $user->assignRole('mahasiswa');

        DiagnosticResult::factory()->create([
            'user_id'    => $user->id,
            'created_at' => today(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/diagnostic/submit', [
            'text_answers' => ['test answer'],
            'audio'        => UploadedFile::fake()->create('audio.wav', 100),
        ]);

        $response->assertStatus(429);
    }

    /** @test */
    public function guest_tidak_bisa_akses_diagnostic()
    {
        $this->postJson('/api/diagnostic/submit', [])->assertStatus(401);
    }
}
```

### Jalankan Test
```bash
php artisan test                                          # semua test
php artisan test --testsuite=Unit                        # unit saja
php artisan test tests/Unit/AdaptiveEngineTest.php       # file spesifik
php artisan test --coverage --min=70                     # dengan coverage
```

---

## 18. DEVELOPMENT PRIORITY & TIMELINE

> ⚠️ **DEADLINE: Awal Juli 2025. WAJIB ikuti urutan phase ini.**

### Phase 1 — MVP Core (Target: 2 Minggu Pertama)
```
✅ Setup project Laravel 12 + Livewire Starter Kit
✅ Database migration + RoleSeeder + ModuleSeeder
✅ Auth: register, login, logout (3 role)
✅ Middleware: EnsureDailyTestLimit
✅ DiagnosticTest Livewire (wizard 3 step: grammar, vocab, speaking)
✅ NlpAnalysisService (GPT-4o Mini)
✅ SpeechToTextService (Whisper API)
✅ ProcessSpeakingAudio Job (queue)
✅ ScoringService
✅ AdaptiveEngineService (PRIORITAS UTAMA)
✅ FeedbackService (teks)
✅ Dashboard Livewire (radar chart 3 skill + feedback teks)
```

### Phase 2 — Learning System (Target: Minggu 3-4)
```
⬜ Learning roadmap dengan status unlock/lock per modul
⬜ Module detail page (Financial Presentation + Client Meeting)
⬜ Exercise/quiz per modul (dari bank soal dosen)
⬜ Progress tracking mahasiswa
⬜ Re-evaluation trigger (jika 70% modul selesai)
```

### Phase 3 — Admin & Laporan (Target: Minggu 5-6)
```
⬜ Filament: QuestionResource (input bank soal dosen)
⬜ Filament: ModuleResource
⬜ Filament: DiagnosticResultResource (laporan berkala dosen)
⬜ Import bank soal via CSV/Excel (dari dosen)
⬜ Laporan performa mahasiswa per periode
```

### Phase 4 — Polish & Testing (Target: Minggu 7)
```
⬜ Unit test AdaptiveEngine (semua skenario path)
⬜ Feature test endpoint utama
⬜ UI polish: loading state, error handling
⬜ Performance test: 100+ user concurrent
⬜ Security review: validasi input, API key
⬜ Deployment ke VPS
```

### Yang TIDAK Boleh Diprioritaskan di Awal
```
❌ Animasi UI yang kompleks
❌ Dark mode
❌ Mobile responsive
❌ Notifikasi real-time (push notification)
❌ Fitur tambahan di luar scope PDF
```

---

## 19. DEPLOYMENT & VPS

### Spesifikasi VPS yang Dibutuhkan
```
OS      : Ubuntu 22.04 LTS
CPU     : 2-4 vCPU
RAM     : 4GB (Whisper butuh RAM cukup jika self-host)
Storage : 20GB SSD
Rekomendasi VPS IDN:
  - IDCloudHost  : ~Rp 100.000-150.000/bulan
  - Niagahoster  : ~Rp 120.000-180.000/bulan
  - Dewaweb      : ~Rp 150.000-200.000/bulan
```

### Setup Server (Ubuntu 22.04)
```bash
# Update server
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath

# Install MySQL
sudo apt install -y mysql-server
sudo mysql_secure_installation

# Install Nginx
sudo apt install -y nginx

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install Redis (untuk production queue & cache)
sudo apt install -y redis-server
```

### Nginx Config
```nginx
server {
    listen 80;
    server_name apels.yourdomain.com;
    root /var/www/apels/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Batasi upload audio maks 15MB
    client_max_body_size 15M;
}
```

### Supervisor (Queue Worker)
```ini
# /etc/supervisor/conf.d/apels-queue.conf
[program:apels-queue]
command=php /var/www/apels/artisan queue:work redis --tries=3 --timeout=60 --sleep=3
directory=/var/www/apels
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/apels-queue.log
```

### Production Checklist
```bash
# Di server
git clone <repo> /var/www/apels
cd /var/www/apels
composer install --no-dev --optimize-autoloader
npm install && npm run build

# .env production
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis

# Optimasi
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Permission
sudo chown -R www-data:www-data /var/www/apels
sudo chmod -R 755 /var/www/apels/storage

# Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start apels-queue:*

# Scheduler (tambah ke crontab)
* * * * * cd /var/www/apels && php artisan schedule:run >> /dev/null 2>&1
```

---

## QUICK REFERENCE

### Artisan Commands
```bash
php artisan serve                            # dev server
php artisan migrate:fresh --seed            # reset + seed ulang
php artisan make:livewire Student/Dashboard # buat Livewire component
php artisan make:model NamaModel -m         # model + migrasi
php artisan make:job NamaJob                # queue job
php artisan queue:work                       # jalankan queue (dev)
php artisan queue:failed                    # lihat job gagal
php artisan queue:retry all                 # retry semua job gagal
php artisan test                             # semua test
php artisan optimize:clear                  # bersihkan semua cache
php artisan tinker                           # REPL interaktif
```

### Urutan Debug jika Ada Bug
```
1. Cek log    → storage/logs/laravel.log
2. Cek queue  → php artisan queue:failed
3. Clear cache → php artisan optimize:clear
4. Cek env    → php artisan config:clear
5. dd() / dump() → debug cepat di kode
6. Tinker     → php artisan tinker → test logic interaktif
```

---

> 📌 **Reminder Akhir:**
>
> Nilai utama APELS ada pada `AdaptiveEngineService`.
> Selama engine menghasilkan learning path yang tepat berdasarkan
> 3 skor (speaking, grammar, vocabulary) dengan threshold yang benar,
> sistem sudah **"hidup"** dan siap digunakan mahasiswa.
>
> **Deadline: Awal Juli. Fokus Phase 1 dulu. Jangan melebar.**
