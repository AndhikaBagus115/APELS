# PRODUCT REQUIREMENTS DOCUMENT — APELS

## Adaptive Professional English Learning System

---

## 1. Ringkasan Eksekutif

APELS (Adaptive Professional English Learning System) adalah platform pembelajaran Bahasa Inggris berbasis web yang dirancang untuk mahasiswa. Sistem ini bukan sekadar LMS biasa — fokus utamanya adalah kemampuan adaptif yang mampu berpikir dan memutuskan jalur belajar secara otomatis berdasarkan hasil tes setiap mahasiswa.

**Tiga pilar utama sistem:**

1. **Diagnosis akurat** — mengukur 3 skill: Speaking, Grammar, Vocabulary
2. **Adaptive Engine** — rule-based decision system (if-then) yang menentukan learning path
3. **Feedback otomatis** — respons teks seolah ada tutor pribadi

---

## 2. Tujuan & Sasaran

### 2.1 Tujuan Utama

- Membangun adaptive engine yang mampu menentukan learning path berbeda untuk setiap mahasiswa secara otomatis
- Mengintegrasikan API OpenAI (Whisper + GPT-4o Mini) untuk analisis kemampuan bahasa
- Menyediakan dashboard yang informatif dengan visualisasi skill berbasis radar chart
- Membangun admin panel yang user-friendly untuk dosen mengelola konten tanpa bergantung pada developer

### 2.2 Indikator Keberhasilan

| Indikator | Target |
|-----------|--------|
| Adaptive Engine akurat | 8 learning path sesuai threshold |
| Response time diagnostic | < 60 detik (termasuk speaking queue) |
| Mahasiswa aktif | 100+ mahasiswa |
| Uptime sistem | 99%+ |
| Budget API bulanan | ≤ $20/bulan |

---

## 3. Pengguna & Role

Sistem APELS memiliki tiga role pengguna dengan akses dan fungsi yang berbeda:

| Role | Akses |
|------|-------|
| Admin | Full akses: kelola semua data, user, soal, modul |
| Dosen | Input soal, lihat laporan berkala mahasiswa |
| Mahasiswa | Tes diagnosis, dashboard, learning path, exercises |

### 3.1 User Journey — Mahasiswa

1. Daftar akun & verifikasi email
2. Login ke sistem
3. Mengerjakan Diagnostic Test (1x per hari): speaking audio + grammar teks + vocabulary teks
4. Menunggu hasil proses (grammar & vocab langsung, speaking via queue < 60 detik)
5. Melihat Dashboard: radar chart 3 skill + feedback teks + learning path
6. Mengakses modul yang ter-unlock sesuai path
7. Mengerjakan latihan soal per modul
8. Setelah 70% modul selesai → sistem sarankan tes ulang
9. Siklus berulang → learning path diperbarui

### 3.2 User Journey — Dosen

1. Login ke panel dosen (Filament)
2. Input atau import bank soal (grammar & vocabulary)
3. Kelola modul pembelajaran
4. Lihat laporan berkala performa mahasiswa
5. Filter laporan per mahasiswa / per periode

---

## 4. Fitur & Requirements

### 4.1 Diagnostic Test (Wajib MVP)

Tiga komponen tes yang wajib ada:

| Komponen | Input | Proses | Output |
|----------|-------|--------|--------|
| Speaking | Audio rekaman | OpenAI Whisper → Transcript → Scoring | Skor 0-100 |
| Grammar | Jawaban teks | OpenAI GPT-4o Mini → Analisis | Skor 0-100 |
| Vocabulary | Jawaban teks | OpenAI GPT-4o Mini → Analisis | Skor 0-100 |

**Batasan:**
- Maksimal 1 tes per hari per mahasiswa
- Audio maksimal 10MB (format: wav, mp3, webm, ogg)
- Speaking diproses async via queue (tidak blocking)

### 4.2 Adaptive Engine (PRIORITAS UTAMA)

**Threshold skor (sesuai dokumen spesifikasi):**

| Kategori | Range Skor | Jalur |
|----------|-----------|-------|
| Rendah | < 60 | Jalur dasar |
| Sedang | 60–70 | Jalur intermediate |
| Tinggi | > 70 | Jalur profesional/advanced |

**Delapan learning path yang tersedia:**

| # | Path Key | Label | Kondisi |
|---|----------|-------|---------|
| 1 | `emergency_foundation` | Foundation Level | Semua skor < 50 |
| 2 | `fundamental_communication` | Communication Fundamentals | Speaking < 60 DAN Vocabulary < 60 |
| 3 | `basic_speaking` | Speaking Starter | Speaking < 60 |
| 4 | `grammar_foundation` | Grammar Builder | Grammar < 60 |
| 5 | `vocabulary_builder` | Vocabulary Builder | Vocabulary < 60 |
| 6 | `intermediate_path` | Intermediate English | Semua skor 60-70 |
| 7 | `professional_simulation` | Professional Simulation | Speaking ≥ 70 DAN Grammar ≥ 70 |
| 8 | `industry_ready` | Industry Ready | Speaking ≥ 80 DAN Semua ≥ 75 |

> ⚠️ Rule dievaluasi dari kondisi paling spesifik ke paling umum.

### 4.3 Modul Pembelajaran (Wajib MVP)

Dua modul wajib tersedia di MVP awal:

| Modul | Deskripsi | Syarat Unlock |
|-------|-----------|---------------|
| Financial Presentation | Simulasi presentasi laporan keuangan | Speaking ≥ 70 DAN Grammar ≥ 70 |
| Client Meeting | Simulasi komunikasi dengan klien | Speaking ≥ 70 DAN Grammar ≥ 70 |

> Modul level basic selalu ter-unlock untuk semua mahasiswa.

### 4.4 Dashboard Mahasiswa

- **Radar Chart 3 skill** (Speaking, Grammar, Vocabulary) — menggunakan ApexCharts
- **Label overall readiness** (Foundation / Intermediate / Professional / Industry-Ready)
- **Feedback teks otomatis** — hasil dari FeedbackService
- **Notifikasi** "Speaking sedang diproses..." selama queue berjalan (polling 5 detik)
- **Adaptive Learning Roadmap** — daftar modul dengan status: Completed / In Progress / Locked
- **Progress bar** persentase penyelesaian modul

### 4.5 Admin Panel (Filament)

| Resource | Fungsi | Akses |
|----------|--------|-------|
| UserResource | Kelola user & role | Admin |
| QuestionResource | Input & kelola bank soal | Admin, Dosen |
| ModuleResource | Kelola konten modul | Admin, Dosen |
| DiagnosticResultResource | Laporan berkala | Admin, Dosen (read-only) |

**Fitur tambahan admin:**
- Import bank soal via CSV/Excel
- Filter laporan per mahasiswa / per periode

### 4.6 Fitur yang TIDAK ada di MVP

- ❌ Animasi UI yang kompleks dan dark mode
- ❌ Mobile responsive (cukup desktop/laptop)
- ❌ Notifikasi real-time (push notification)
- ❌ Branding kampus khusus
- ❌ Lecturer dashboard real-time (cukup laporan berkala)
- ❌ Fitur tambahan di luar scope dokumen spesifikasi

---

## 5. Alur Sistem

### 5.1 Alur Diagnostic Test End-to-End

```
1. Mahasiswa membuka halaman Diagnostic Test
2. Sistem cek middleware: sudah tes hari ini? → Jika ya, tampilkan "Coba lagi besok"
3. Mahasiswa mengerjakan 3 tahap: grammar (teks) → vocabulary (teks) → speaking (audio)
4. Submit: teks dikirim ke GPT-4o Mini (sinkron), audio ke queue (async)
5. GPT-4o Mini mengembalikan grammar_score dan vocab_score
6. Hasil grammar & vocab disimpan ke diagnostic_results, speaking = 0 (sementara)
7. Queue job ProcessSpeakingAudio dijalankan di background
8. Whisper API memproses audio → mengembalikan transcript
9. Speaking score dihitung dari transcript → diupdate ke diagnostic_results
10. AdaptiveEngineService dijalankan → menentukan learning path
11. FeedbackService dijalankan → menghasilkan teks feedback
12. Dashboard diperbarui → mahasiswa melihat hasil lengkap
```

### 5.2 Alur Adaptive Engine

```
1. Engine menerima 3 skor: speaking, grammar, vocabulary
2. evaluateRules() dievaluasi dari kondisi paling spesifik ke paling umum
3. Path key ditentukan (salah satu dari 8 path)
4. resolveModules() menentukan modul mana yang unlock/lock
5. Hasil disimpan ke user_learning_paths dan user_module_progress
6. FeedbackService dipanggil untuk generate teks feedback
7. Semua hasil dikembalikan ke dashboard
```

### 5.3 Alur Re-evaluation

```
1. Mahasiswa menyelesaikan ≥ 70% modul dalam learning path aktif
2. Sistem menampilkan saran "Kamu siap untuk tes ulang!"
3. Mahasiswa mengerjakan Diagnostic Test lagi (batasan 1x/hari tetap berlaku)
4. Engine mengevaluasi ulang → path bisa naik, turun, atau tetap
5. Dashboard dan modul diperbarui sesuai hasil terbaru
```

---

## 6. Arsitektur Teknis

### 6.1 Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | Laravel 12 |
| Reaktif UI | Livewire 3 (Starter Kit) |
| UI Components | Flux UI (bawaan Livewire Starter Kit) |
| Styling | TailwindCSS |
| JS Ringan | Alpine.js |
| Chart | ApexCharts |
| Admin Panel | Filament 3 |
| Database | MySQL 8+ |
| Queue | Laravel Queue (database dev → Redis production) |
| Cache | Laravel Cache (file dev → Redis production) |
| Auth | Laravel built-in (Livewire Starter Kit) |
| PHP | 8.2 - 8.4 |
| Server | VPS Ubuntu 22.04 LTS |

### 6.2 Service Layer Architecture

Semua business logic wajib berada di Service class, bukan di Controller. Pattern ini memastikan kode modular, mudah ditest, dan mudah dimodifikasi.

| Service | Fungsi |
|---------|--------|
| DiagnosticService | Orkestrasi proses tes |
| ScoringService | Hitung & normalisasi skor |
| AdaptiveEngineService | Rule-based engine (CORE) |
| FeedbackService | Generate feedback teks |
| SpeechToTextService | Wrapper OpenAI Whisper API |
| NlpAnalysisService | Wrapper OpenAI GPT-4o Mini |

### 6.3 Database — Tabel Utama

| Tabel | Fungsi |
|-------|--------|
| users | Data pengguna + nim + level |
| diagnostic_results | Hasil tes (3 skor + overall + attempt) |
| user_learning_paths | Learning path aktif per user |
| modules | Daftar modul pembelajaran |
| user_module_progress | Progress per user per modul |
| questions | Bank soal grammar & vocabulary |
| feedbacks | Feedback teks per diagnostic result |

---

## 7. API Eksternal

### 7.1 OpenAI API — Konfigurasi

| Service | Model | Harga |
|---------|-------|-------|
| Speaking STT | OpenAI Whisper API | $0.003/menit audio |
| Grammar & Vocab | OpenAI GPT-4o Mini | $0.15/1M input token |

### 7.2 Estimasi Biaya (100 Mahasiswa, 8 Tes/Bulan)

| Item | Estimasi |
|------|----------|
| Whisper (audio 1-2 menit × 800 tes) | ~$2.40-4.80/bulan |
| GPT-4o Mini (teks analisis × 800 tes) | ~$1-3/bulan |
| **Total estimasi** | **~$6-12/bulan** |

### 7.3 Strategi Efisiensi API

- **Queue async** — speaking diproses di background, tidak blocking user
- **Cache NLP** — hasil analisis teks di-cache 24 jam (hash-based)
- **Limit 1 tes/hari** — middleware memblokir pemakaian berlebih
- **Hapus file audio** — setelah diproses Whisper, file dihapus otomatis (hemat storage)
- **Usage alert** — set alert di $15 dan batas $20/bulan di OpenAI dashboard

---

## 8. Keamanan

| Aspek | Implementasi |
|-------|-------------|
| Authentication | Laravel built-in + Livewire Starter Kit |
| Authorization | Spatie Laravel Permission (3 role) |
| CSRF | Aktif (bawaan Laravel) |
| API Key | Hanya di .env, akses via config() |
| Audio Storage | storage/private (bukan public) |
| Validasi Input | FormRequest untuk semua endpoint |
| Rate Limit | 1 tes/hari via middleware |
| SSL | Let's Encrypt (production) |

---

## 9. Deployment

### 9.1 Spesifikasi VPS

| Parameter | Spesifikasi |
|-----------|-------------|
| OS | Ubuntu 22.04 LTS |
| CPU | 2-4 vCPU |
| RAM | 4GB |
| Storage | 20GB SSD |
| Web Server | Nginx |
| Process Manager | Supervisor (queue worker 2 proses) |

### 9.2 Production Checklist

- [ ] `APP_ENV=production` dan `APP_DEBUG=false` di .env
- [ ] `php artisan optimize` (config:cache + route:cache + view:cache)
- [ ] `npm run build` untuk asset production
- [ ] Supervisor dikonfigurasi untuk queue worker (2 proses)
- [ ] Crontab dikonfigurasi untuk Laravel Scheduler
- [ ] SSL aktif via Let's Encrypt (gratis)
- [ ] Usage limit OpenAI diset: alert $15, batas $20/bulan
- [ ] File .env tidak ter-commit ke repository Git

---

## 10. Development Priority & Timeline

> ⚠️ **Deadline MVP: Awal Juli 2025**

### Phase 1 — MVP Core (Target: 2 Minggu Pertama)

- [ ] Setup project Laravel 12 + Livewire Starter Kit
- [ ] Database migration + RoleSeeder + ModuleSeeder
- [ ] Auth: register, login, logout (3 role)
- [ ] Middleware: EnsureDailyTestLimit
- [ ] DiagnosticTest Livewire (wizard 3 step)
- [ ] NlpAnalysisService (GPT-4o Mini)
- [ ] SpeechToTextService (Whisper API)
- [ ] ProcessSpeakingAudio Job (queue)
- [ ] ScoringService
- [ ] **AdaptiveEngineService (PRIORITAS UTAMA)**
- [ ] FeedbackService (teks)
- [ ] Dashboard Livewire (radar chart + feedback)

### Phase 2 — Learning System (Target: Minggu 3–4)

- [ ] Learning roadmap dengan status unlock/lock per modul
- [ ] Module detail page (Financial Presentation + Client Meeting)
- [ ] Exercise/quiz per modul dari bank soal dosen
- [ ] Progress tracking dan perhitungan persentase
- [ ] Re-evaluation trigger (jika 70% modul selesai)

### Phase 3 — Admin & Laporan (Target: Minggu 5–6)

- [ ] Filament: QuestionResource — input & kelola bank soal
- [ ] Filament: ModuleResource — kelola konten modul
- [ ] Filament: DiagnosticResultResource — laporan berkala dosen
- [ ] Import bank soal via CSV/Excel dari dosen

### Phase 4 — Testing & Deployment (Target: Minggu 7)

- [ ] Unit test AdaptiveEngine (semua 8 skenario path)
- [ ] Feature test endpoint utama (diagnostic submit, auth)
- [ ] UI polish: loading state, error handling, empty state
- [ ] Performance test: 100+ mahasiswa concurrent
- [ ] Security review: validasi input, API key, role access
- [ ] Deployment ke VPS production

---

## 11. Standarisasi & Konvensi Kode

### 11.1 Naming Convention

| Elemen | Konvensi | Contoh |
|--------|----------|--------|
| Model | PascalCase singular | `DiagnosticResult` |
| Controller | PascalCase + Controller | `DiagnosticController` |
| Service | PascalCase + Service | `AdaptiveEngineService` |
| Livewire | PascalCase | `Dashboard`, `DiagnosticTest` |
| Job | PascalCase + verb | `ProcessSpeakingAudio` |
| Migration | snake_case deskriptif | `create_diagnostic_results_table` |
| Route name | kebab-case | `learning-path` |
| Blade view | kebab-case | `dashboard.blade.php` |
| Variable PHP | camelCase | `$pathKey`, `$userId` |
| Config key | snake_case | `learning_paths` |

### 11.2 API Response Format (Standar)

Semua endpoint API wajib mengembalikan response dengan format berikut:

**Success:**
```json
{
    "status": "success",
    "message": "Tes berhasil diproses",
    "data": { ... }
}
```

**Error:**
```json
{
    "status": "error",
    "message": "Deskripsi error",
    "errors": { ... },
    "code": 422
}
```

---

## 12. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|--------|--------|----------|
| API OpenAI down | Tes tidak bisa diproses | Fallback skor = 0, retry 3x, log error |
| Budget API melebihi batas | Biaya membengkak | Limit 1 tes/hari, cache NLP, alert $15 |
| Audio terlalu besar | Upload gagal | Limit 10MB, validasi di frontend & backend |
| Queue job gagal | Speaking tidak terproses | Retry 3x, failed job handler, notif admin |
| Concurrent user tinggi | Sistem lambat | Queue async, Redis cache, Supervisor 2 worker |

---

## 13. Glosarium

| Istilah | Definisi |
|---------|----------|
| Adaptive Engine | Sistem rule-based yang menentukan learning path berdasarkan skor |
| Learning Path | Jalur belajar yang ditentukan berdasarkan hasil diagnostic test |
| Diagnostic Test | Tes diagnosis 3 skill (speaking, grammar, vocabulary) |
| Radar Chart | Visualisasi 3 skill dalam bentuk chart pentagon/segitiga |
| Queue/Job | Proses background untuk task yang membutuhkan waktu lama |
| Threshold | Batas skor yang menentukan kategori kemampuan |
| Re-evaluation | Proses evaluasi ulang setelah mahasiswa menyelesaikan 70% modul |
| STT | Speech-to-Text (konversi audio ke teks) |
| NLP | Natural Language Processing (analisis bahasa) |

---

> 📌 **Dokumen ini dibuat berdasarkan spesifikasi resmi APELS dan hasil konfirmasi dengan pemilik project.**
> Panduan teknis lengkap tersedia di `CLAUDE.md`
