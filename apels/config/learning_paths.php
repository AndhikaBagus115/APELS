<?php

/*
|--------------------------------------------------------------------------
| APELS Learning Paths Master Configuration
|--------------------------------------------------------------------------
|
| Single source of truth untuk delapan learning path APELS.
|
| Digunakan oleh:
|   - AdaptiveEngineService::run()           => mengambil `label` via
|     config("learning_paths.{$pathKey}.label") untuk field `path_label`
|     pada response. (Req 9.5)
|   - Filament ModuleResource                => populate Select `path_key`
|     dari array_keys(config('learning_paths')) sebagai whitelist valid.
|     (Req 21.3)
|   - Dashboard mahasiswa                    => menampilkan label readiness
|     path aktif. (Req 13.2)
|   - LearningPathSeeder & dokumentasi audit => mendokumentasikan threshold
|     rule per path agar dapat direview oleh dosen/admin.
|
| Setiap entri WAJIB memiliki:
|   - label     (string) Label tampilan Dashboard sesuai Req 13.2.
|   - color     (string) Token warna Tailwind untuk badge/UI.
|   - condition (string) Threshold rule human-readable. Implementasi
|                        deterministik berada di AdaptiveEngineService::evaluateRules
|                        (Req 9.1) — string ini hanya untuk dokumentasi/audit.
|   - modules   (array)  Daftar slug modul yang relevan dengan path ini.
|                        Slug ini menjadi acuan ModuleSeeder dan harus
|                        konsisten dengan kolom `modules.path_key` (Req 11).
|
| Daftar `path_key` yang valid (Req 9.3 — totality AdaptiveEngineService):
|   emergency_foundation, fundamental_communication, basic_speaking,
|   grammar_foundation, vocabulary_builder, intermediate_path,
|   professional_simulation, industry_ready
|
| Sumber: CLAUDE.md §10 (Adaptive Engine — Learning Path Master).
|
*/

return [

    // -------------------------------------------------------------------------
    // CRITICAL: semua skill rendah (< 50)
    // Threshold AdaptiveEngineService: speaking < 50 AND grammar < 50 AND vocabulary < 50
    // Req 9.1.1
    // -------------------------------------------------------------------------
    'emergency_foundation' => [
        'label'     => 'Foundation', // Req 13.2
        'color'     => 'red',
        'condition' => 'Speaking < 50 AND Grammar < 50 AND Vocabulary < 50',
        'modules'   => [
            'basic_english',
            'simple_grammar',
            'daily_vocabulary',
        ],
    ],

    // -------------------------------------------------------------------------
    // MULTI-SKILL RENDAH: speaking dan vocabulary keduanya rendah
    // Threshold AdaptiveEngineService: speaking < 60 AND vocabulary < 60
    // Req 9.1.2
    // -------------------------------------------------------------------------
    'fundamental_communication' => [
        'label'     => 'Communication Fundamentals', // Req 13.2
        'color'     => 'orange',
        'condition' => 'Speaking < 60 AND Vocabulary < 60',
        'modules'   => [
            'pronunciation',
            'basic_speaking',
            'vocab_daily',
        ],
    ],

    // -------------------------------------------------------------------------
    // SINGLE SKILL RENDAH: speaking saja yang rendah
    // Threshold AdaptiveEngineService: speaking < 60 (setelah multi-skill check)
    // Req 9.1.3
    // -------------------------------------------------------------------------
    'basic_speaking' => [
        'label'     => 'Speaking Starter', // Req 13.2
        'color'     => 'yellow',
        'condition' => 'Speaking < 60',
        'modules'   => [
            'pronunciation',
            'fluency_basic',
        ],
    ],

    // -------------------------------------------------------------------------
    // SINGLE SKILL RENDAH: grammar saja yang rendah
    // Threshold AdaptiveEngineService: grammar < 60 (setelah cek di atas)
    // Req 9.1.4
    // -------------------------------------------------------------------------
    'grammar_foundation' => [
        'label'     => 'Grammar Builder', // Req 13.2
        'color'     => 'blue',
        'condition' => 'Grammar < 60',
        'modules'   => [
            'basic_tenses',
            'sentence_structure',
        ],
    ],

    // -------------------------------------------------------------------------
    // SINGLE SKILL RENDAH: vocabulary saja yang rendah
    // Threshold AdaptiveEngineService: vocabulary < 60 (setelah cek di atas)
    // Req 9.1.5
    // -------------------------------------------------------------------------
    'vocabulary_builder' => [
        'label'     => 'Vocabulary Builder', // Req 13.2
        'color'     => 'cyan',
        'condition' => 'Vocabulary < 60',
        'modules'   => [
            'vocab_daily',
            'accounting_terms',
        ],
    ],

    // -------------------------------------------------------------------------
    // INTERMEDIATE: default jika tidak masuk kategori lain
    // Threshold AdaptiveEngineService: skor menengah (60-70) di seluruh skill
    // Req 9.1.8 (default branch)
    // -------------------------------------------------------------------------
    'intermediate_path' => [
        'label'     => 'Intermediate English', // Req 13.2
        'color'     => 'indigo',
        'condition' => 'Semua skor 60-70',
        'modules'   => [
            'business_writing',
            'presentation_skills',
        ],
    ],

    // -------------------------------------------------------------------------
    // PROFESSIONAL: cocok untuk simulasi profesional
    // Threshold AdaptiveEngineService: speaking >= 70 AND grammar >= 70
    // Req 9.1.7
    // Modul wajib MVP: financial_presentation, client_meeting (Req 11.1, 11.2)
    // -------------------------------------------------------------------------
    'professional_simulation' => [
        'label'     => 'Professional Simulation', // Req 13.2
        'color'     => 'purple',
        'condition' => 'Speaking >= 70 AND Grammar >= 70',
        'modules'   => [
            'financial_presentation', // Req 11.1 (MVP wajib)
            'client_meeting',         // Req 11.2 (MVP wajib)
        ],
    ],

    // -------------------------------------------------------------------------
    // INDUSTRY READY: siap kerja di lingkungan internasional
    // Threshold AdaptiveEngineService: speaking >= 80 AND grammar >= 75 AND vocabulary >= 75
    // Req 9.1.6
    // Path ini meng-unlock modul advanced_negotiation di samping dua modul MVP
    // (Req 11.3)
    // -------------------------------------------------------------------------
    'industry_ready' => [
        'label'     => 'Industry Ready', // Req 13.2
        'color'     => 'green',
        'condition' => 'Speaking >= 80 AND Grammar >= 75 AND Vocabulary >= 75',
        'modules'   => [
            'financial_presentation', // Req 11.3
            'client_meeting',         // Req 11.3
            'advanced_negotiation',   // Req 11.3
        ],
    ],

];
