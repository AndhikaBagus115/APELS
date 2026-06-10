<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * ScoringService — pure weighted-overall and normalization helper.
 *
 * Bobot tetap (Req 8.1):
 *   speaking = 0.40, grammar = 0.30, vocabulary = 0.30 (sum = 1.00).
 *
 * Dipakai oleh DiagnosticService::runEngineAndFeedback untuk update
 * `diagnostic_results.overall` setelah skor speaking final tersedia.
 *
 * Properti yang akan diuji via PBT:
 *  - Property 1: bounds [0..100] + missing-key fallback + determinism (Req 8.2-8.4).
 *  - Property 2: normalize boundary correctness + monotonicity (Req 8.5).
 */
class ScoringService
{
    /**
     * Bobot per skill — tetap konstan sesuai Req 8.1.
     *
     * @var array<string, float>
     */
    private array $weights = [
        'speaking'   => 0.40,
        'grammar'    => 0.30,
        'vocabulary' => 0.30,
    ];

    /**
     * Hitung overall score sebagai weighted average.
     *
     * - Skor key absent diperlakukan sebagai 0 (Req 8.4).
     * - Skor di luar 0..100 atau non-numeric → InvalidArgumentException (Req 8.3).
     *
     * @param  array<string, int|float>  $scores Keys: speaking, grammar, vocabulary.
     * @return float                             Overall pada [0.0, 100.0], dibulatkan 2 desimal (Req 8.2).
     *
     * @throws InvalidArgumentException
     */
    public function calculateOverall(array $scores): float
    {
        $sum = 0.0;
        foreach ($this->weights as $skill => $weight) {
            $value = $scores[$skill] ?? 0;
            if (!is_numeric($value)) {
                throw new InvalidArgumentException("Score for {$skill} must be numeric, got " . gettype($value));
            }
            if ($value < 0 || $value > 100) {
                throw new InvalidArgumentException("Score for {$skill} must be within 0..100, got {$value}");
            }
            $sum += $value * $weight;
        }
        return round($sum, 2);
    }

    /**
     * Normalisasi `$score` dari rentang [$min, $max] ke integer [0, 100] (Req 8.5).
     *
     * - Jika `$min >= $max` → InvalidArgumentException (cegah pembagian 0).
     * - Jika `$score` di luar [$min, $max] → InvalidArgumentException.
     */
    public function normalize(float $score, float $min = 0.0, float $max = 1.0): int
    {
        if ($min >= $max) {
            throw new InvalidArgumentException("normalize: \$min ({$min}) must be < \$max ({$max})");
        }
        if ($score < $min || $score > $max) {
            throw new InvalidArgumentException("normalize: \$score ({$score}) must be within [{$min}, {$max}]");
        }
        return (int) round((($score - $min) / ($max - $min)) * 100);
    }
}
