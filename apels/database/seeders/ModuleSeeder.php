<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * ModuleSeeder — modul MVP wajib (Req 11) + 1 basic placeholder.
 *
 * Idempotent: gunakan firstOrCreate berdasarkan kombinasi (title, path_key)
 * agar aman dijalankan berkali-kali tanpa duplikasi.
 *
 * Modul yang dibuat:
 *  - financial_presentation (professional, professional_simulation) — Req 11.1
 *  - client_meeting        (professional, professional_simulation) — Req 11.2
 *  - advanced_negotiation  (professional, industry_ready)          — Req 11.3
 *  - basic_english         (basic,        emergency_foundation)    — placeholder
 *                                                                    untuk uji unlock
 *                                                                    level=basic (Req 10.2)
 */
class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'title'       => 'Financial Presentation',
                'description' => 'Simulasi presentasi keuangan untuk audiens internasional. Mencakup struktur opening, penyampaian data, dan handling Q&A.',
                'path_key'    => 'professional_simulation',
                'level'       => 'professional',
                'tag'         => 'advanced',
                'order_index' => 100,
                'content'     => [
                    'sections' => [
                        ['title' => 'Opening Statement',         'estimated_minutes' => 15],
                        ['title' => 'Presenting Financial Data', 'estimated_minutes' => 25],
                        ['title' => 'Handling Q&A',              'estimated_minutes' => 20],
                    ],
                ],
                'is_active'   => true,
            ],
            [
                'title'       => 'Client Meeting',
                'description' => 'Simulasi rapat dengan klien internasional: small talk, agenda, dan negotiating action items.',
                'path_key'    => 'professional_simulation',
                'level'       => 'professional',
                'tag'         => 'advanced',
                'order_index' => 110,
                'content'     => [
                    'sections' => [
                        ['title' => 'Small Talk & Rapport',   'estimated_minutes' => 10],
                        ['title' => 'Setting the Agenda',     'estimated_minutes' => 15],
                        ['title' => 'Action Items & Wrap-up', 'estimated_minutes' => 15],
                    ],
                ],
                'is_active'   => true,
            ],
            [
                'title'       => 'Advanced Negotiation',
                'description' => 'Negosiasi tingkat lanjut: tactics, objection handling, dan closing language.',
                'path_key'    => 'industry_ready',
                'level'       => 'professional',
                'tag'         => 'advanced',
                'order_index' => 120,
                'content'     => [
                    'sections' => [
                        ['title' => 'Negotiation Tactics', 'estimated_minutes' => 20],
                        ['title' => 'Objection Handling',  'estimated_minutes' => 20],
                        ['title' => 'Closing Language',    'estimated_minutes' => 15],
                    ],
                ],
                'is_active'   => true,
            ],
            [
                'title'       => 'Basic English Foundation',
                'description' => 'Pondasi Bahasa Inggris untuk pemula: alfabet, ucapan dasar, dan kalimat sederhana.',
                'path_key'    => 'emergency_foundation',
                'level'       => 'basic',
                'tag'         => 'basic',
                'order_index' => 10,
                'content'     => [
                    'sections' => [
                        ['title' => 'Alphabet & Pronunciation', 'estimated_minutes' => 15],
                        ['title' => 'Basic Greetings',          'estimated_minutes' => 10],
                        ['title' => 'Simple Sentences',         'estimated_minutes' => 20],
                    ],
                ],
                'is_active'   => true,
            ],
        ];

        foreach ($modules as $data) {
            Module::firstOrCreate(
                ['title' => $data['title'], 'path_key' => $data['path_key']],
                $data
            );
        }
    }
}
