<?php

/**
 * APELS — Konfigurasi domain spesifik aplikasi.
 *
 * File ini mengekspos parameter operasional inti APELS yang dibaca lewat
 * helper `config('apels.*')` agar service & middleware dapat memperolehnya
 * secara konsisten tanpa memanggil `env()` di runtime (Req 24.2).
 *
 * Semua nilai di-resolve dari environment variable; default dijaga sesuai
 * spesifikasi pada requirements.md untuk memastikan sistem tetap berfungsi
 * meskipun env tidak di-set (mis. di lingkungan testing/CI).
 *
 * File HARUS pure PHP return array tanpa side-effect / dependency eksternal.
 *
 * @see config/learning_paths.php untuk peta delapan path key.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Daily Test Limit
    |--------------------------------------------------------------------------
    |
    | Jumlah maksimal Diagnostic Test yang boleh dilakukan seorang mahasiswa
    | dalam satu hari kalender (timezone aplikasi). Dibaca oleh
    | `EnsureDailyTestLimit` middleware. Default = 1.
    |
    | Requirement: 3 (Pembatasan Satu Diagnostic Test Per Hari).
    */
    'daily_test_limit' => env('APELS_DAILY_TEST_LIMIT', 1),

    /*
    |--------------------------------------------------------------------------
    | Audio Maximum Size (MB)
    |--------------------------------------------------------------------------
    |
    | Ukuran maksimum file audio Diagnostic Test dalam megabyte. Digunakan
    | oleh `SubmitDiagnosticRequest` (`audio|max:audio_max_mb*1024`).
    | Default = 10 MB.
    |
    | Requirement: 5.3 (Validasi Input Diagnostic Test).
    */
    'audio_max_mb' => env('APELS_AUDIO_MAX_MB', 10),

    /*
    |--------------------------------------------------------------------------
    | Speech-to-Text Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Batas waktu (detik) panggilan OpenAI Whisper API di
    | `SpeechToTextService::analyze`. Jika melewati timeout, service akan
    | mencatat error dan mengembalikan skor 0. Default = 60 detik.
    |
    | Requirement: 25.3 (Timeout External API), 6.7 (Whisper failure fallback).
    */
    'stt_timeout' => env('APELS_STT_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | NLP Analysis Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Batas waktu (detik) panggilan OpenAI GPT-4o Mini di
    | `NlpAnalysisService::analyze`. Jika melewati timeout / parse error,
    | service mengembalikan `['grammar_score' => 0, 'vocab_score' => 0]`.
    | Default = 30 detik.
    |
    | Requirement: 25.3 (Timeout External API), 7.4 (NLP failure fallback).
    */
    'nlp_timeout' => env('APELS_NLP_TIMEOUT', 30),

];
