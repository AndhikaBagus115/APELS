<?php

namespace App\Services;

/**
 * FeedbackService — pure feedback text generator (Req 12).
 *
 * Menghasilkan teks feedback Bahasa Indonesia berdasarkan tiga skor skill
 * (Speaking, Grammar, Vocabulary) dan learning path key aktif.
 *
 * Aturan threshold per skill (Req 12.2, 12.3):
 *   skor < 60        → *_low
 *   60 ≤ skor < 70   → *_mid
 *   skor ≥ 70        → *_high
 *
 * Output struktur (Req 12.5):
 *   ['message' => string, 'details' => array<int, string>, 'next_focus' => string]
 *
 * Service ini bersifat **pure** (tanpa DB/IO) — cocok untuk Property-Based
 * Test. Properti yang akan diuji (Property 7):
 *  - Threshold determinism per skill independen.
 *  - `details` berisi 4 string: 3 template per skill + 1 kalimat next_focus.
 *  - `message` = gabungan elemen `details` dengan separator spasi.
 *  - Pemanggilan dengan input identik → output identik.
 *
 * Bahasa output: Indonesia (Req 12.8).
 */
class FeedbackService
{
    /**
     * Sembilan template per skill × level (low/mid/high) dalam Bahasa Indonesia.
     * Naskah ringkas, konkret, dan menghindari kalimat berlebihan.
     *
     * @var array<string, array<string, string>>
     */
    private array $templates = [
        'speaking' => [
            'low'  => 'Speaking kamu masih perlu banyak latihan. Mulai dari pelafalan dasar dan kalimat pendek.',
            'mid'  => 'Speaking kamu sudah cukup baik. Tingkatkan kelancaran dengan banyak latihan percakapan.',
            'high' => 'Speaking kamu kuat. Pertahankan dengan simulasi percakapan profesional.',
        ],
        'grammar' => [
            'low'  => 'Grammar kamu masih perlu penguatan. Pelajari ulang struktur kalimat dan tense dasar.',
            'mid'  => 'Grammar kamu sudah lumayan. Fokus ke kompleksitas kalimat dan akurasi tenses.',
            'high' => 'Grammar kamu solid. Lanjutkan dengan latihan business writing.',
        ],
        'vocabulary' => [
            'low'  => 'Vocabulary kamu masih terbatas. Tambah kosakata harian secara konsisten.',
            'mid'  => 'Vocabulary kamu sedang berkembang. Perluas dengan istilah profesional bidangmu.',
            'high' => 'Vocabulary kamu luas. Asah dengan terminologi industri spesifik.',
        ],
    ];

    /**
     * Peta `path_key` → frase next_focus dalam Bahasa Indonesia (Req 12.4, 12.7).
     * Untuk path_key yang tidak dikenal, fallback "pengembangan skill lebih lanjut".
     *
     * @var array<string, string>
     */
    private array $pathNextFocus = [
        'emergency_foundation'      => 'pondasi Bahasa Inggris dasar',
        'fundamental_communication' => 'komunikasi sehari-hari yang lancar',
        'basic_speaking'            => 'kelancaran berbicara',
        'grammar_foundation'        => 'penguatan grammar',
        'vocabulary_builder'        => 'memperkaya kosakata',
        'intermediate_path'         => 'kemampuan menengah dan business writing',
        'professional_simulation'   => 'simulasi profesional Financial Presentation dan Client Meeting',
        'industry_ready'            => 'kesiapan industri dengan negosiasi tingkat lanjut',
    ];

    /**
     * Generate feedback berdasarkan tiga skor (0..100) dan learning path key aktif.
     *
     * Skor key absent diperlakukan sebagai 0 (sejalan dengan ScoringService).
     *
     * @param  array<string, int|float>  $scores  Keys: speaking, grammar, vocabulary.
     * @param  string                    $pathKey Salah satu dari delapan path key valid;
     *                                            nilai tak dikenal akan fallback ke teks default.
     * @return array{message: string, details: list<string>, next_focus: string}
     */
    public function generate(array $scores, string $pathKey): array
    {
        $speaking   = (int) ($scores['speaking']   ?? 0);
        $grammar    = (int) ($scores['grammar']    ?? 0);
        $vocabulary = (int) ($scores['vocabulary'] ?? 0);

        $details = [
            $this->templates['speaking']  [$this->pickLevel($speaking)],
            $this->templates['grammar']   [$this->pickLevel($grammar)],
            $this->templates['vocabulary'][$this->pickLevel($vocabulary)],
        ];

        $nextFocus = $this->pathNextFocus[$pathKey] ?? 'pengembangan skill lebih lanjut';
        $details[] = "Langkah berikutnya: fokus pada {$nextFocus}.";

        return [
            'message'    => implode(' ', $details),
            'details'    => $details,
            'next_focus' => $nextFocus,
        ];
    }

    /**
     * Pilih level template berdasarkan threshold (Req 12.2, 12.3):
     *   < 60        → 'low'
     *   60..69      → 'mid'
     *   ≥ 70        → 'high'
     */
    private function pickLevel(int $score): string
    {
        if ($score < 60) {
            return 'low';
        }
        if ($score < 70) {
            return 'mid';
        }
        return 'high';
    }
}
