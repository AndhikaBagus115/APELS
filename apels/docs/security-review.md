# Security Review Checklist — APELS Platform

Last reviewed: Phase 4 implementation

## 1. Credential & Secret Management (Req 24.1-24.3)

- [x] `OPENAI_API_KEY` dibaca hanya dari `.env` via `config('openai.api_key')` — tidak ada hardcode di source code
- [x] `.env` terdaftar di `.gitignore` — tidak pernah di-commit
- [x] `.env.example` tersedia dengan placeholder values — tidak ada secrets nyata
- [x] Semua service mengakses API key via `config()` bukan `env()` langsung (Req 24.2)
- [x] Jika `OPENAI_API_KEY` kosong → service mengembalikan skor 0 + log error (Req 24.4)

## 2. Input Validation (Req 5.1-5.5)

- [x] `SubmitDiagnosticRequest` memvalidasi `text_answers` (array, 3-20 elemen, 5-500 karakter)
- [x] `SubmitDiagnosticRequest` memvalidasi `audio` (mime: wav/mp3/webm/ogg, max 10 MB)
- [x] HTTP 422 dengan error detail per field dikembalikan saat validasi gagal
- [x] CSV import divalidasi baris per baris sebelum insert ke database
- [x] FormRequest digunakan di seluruh API endpoint — tidak ada mass assignment dari raw request

## 3. Authentication & Authorization (Req 2.1-2.10)

- [x] Spatie Permission middleware `role:mahasiswa`, `role:dosen` dipasang di route groups
- [x] API endpoint `POST /api/diagnostic/submit` dilindungi middleware `auth`
- [x] Filament panel dilindungi via `canAccessPanel()` — mahasiswa tidak bisa akses `/admin`
- [x] `UserResource` hanya bisa diakses `admin`; `canAccess()` return false untuk dosen/mahasiswa
- [x] `DiagnosticResultResource`: dosen read-only, actions create/edit/delete hidden untuk dosen
- [x] Guest mengakses web route → redirect ke `/login`
- [x] Guest mengakses API → HTTP 401

## 4. Audio File Storage (Req 5.5, 23.1-23.4)

- [x] Audio file disimpan ke disk `private` (`storage/app/private`) — tidak accessible via public URL
- [x] `Storage::disk('private')` dengan `serve => false` — tidak ada symlink ke public
- [x] Audio dihapus setelah `ProcessSpeakingAudio` selesai (sukses maupun gagal)
- [x] File tidak ditemukan → log error, return skor 0 (tidak crash)

## 5. Queue & Job Security (Req 6.8-6.9)

- [x] `ProcessSpeakingAudio` job: retry 3x dengan backoff 5s
- [x] `failed()` handler: update DiagnosticResult + hapus audio + log error
- [x] Job tidak membocorkan sensitive data ke log (hanya user_id, operasi, pesan error)

## 6. Role Policy di Filament Resources

- [x] `UserResource::canAccess()` → `hasRole('admin')` only
- [x] `QuestionResource::canAccess()` → `hasAnyRole(['admin', 'dosen'])`
- [x] `ModuleResource::canAccess()` → `hasAnyRole(['admin', 'dosen'])`
- [x] `DiagnosticResultResource::canCreate()` → `hasRole('admin')` only

## 7. CSRF Protection (Req 1.6)

- [x] Laravel built-in CSRF protection aktif untuk semua route web POST/PUT/PATCH/DELETE
- [x] API routes tidak menggunakan CSRF (session-based auth dengan web guard)

## 8. Error Logging & Information Disclosure

- [x] Login gagal mengembalikan pesan generik (tidak membocorkan email vs password) — `auth.failed`
- [x] API error responses tidak mengandung stack trace di production (`APP_DEBUG=false`)
- [x] Service layer menggunakan `Log::error` dengan konteks yang aman (tidak log API keys, passwords)

## 9. Dependency Audit

- [x] `composer audit --no-dev` dijalankan setelah instalasi — tidak ada vulnerabilities pada direct production deps
- [ ] Jadwalkan re-audit rutin saat update package (disarankan: sekali per bulan)

## 10. Production Checklist (Req 32.3)

- [ ] Set `APP_ENV=production` dan `APP_DEBUG=false`
- [ ] Jalankan `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Setup SSL via Let's Encrypt (lihat `docs/deploy-vps.md`)
- [ ] Supervisor dikonfigurasi untuk 2+ queue workers (lihat `deploy/supervisor/apels-worker.conf`)
- [ ] Review log permissions (`storage/logs` tidak publicly readable)

## Items Requiring Manual Testing

- [ ] Penetration test pada endpoint diagnostic submit (file upload bypass attempts)
- [ ] Test akses direct URL audio file (`storage/app/private/diagnostic-audio/*`) — harus 403/404
- [ ] Test role escalation: apakah mahasiswa bisa akses `/admin` secara langsung
- [ ] Verifikasi rate limiting pada login (5 attempts/60s via `ensureIsNotRateLimited`)
