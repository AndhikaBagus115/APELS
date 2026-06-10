<?php

/**
 * Unit + Property tests for FeedbackService (Req 12.1-12.8, 34.5).
 *
 * Property 7: threshold determinism, structural completeness.
 */

use App\Services\FeedbackService;

beforeEach(function () {
    $this->service = new FeedbackService();
});

// ---- Threshold selection (Req 12.2, 12.3) ----

test('FeedbackService: score < 60 selects *_low', function () {
    $r = $this->service->generate(makeScores(59, 59, 59), 'intermediate_path');
    expect($r['message'])->toContain('masih perlu');
});

test('FeedbackService: score 60-69 selects *_mid', function () {
    $r = $this->service->generate(makeScores(65, 65, 65), 'intermediate_path');
    expect($r['message'])->toContain('sudah');
});

test('FeedbackService: score >= 70 selects *_high', function () {
    $r = $this->service->generate(makeScores(70, 70, 70), 'professional_simulation');
    // All 3 skills at 70 → all high templates
    expect($r['message'])
        ->toContain('kuat'); // speaking_high
});

test('FeedbackService: threshold boundary 60 is mid not low', function () {
    $r = $this->service->generate(makeScores(60, 60, 60), 'intermediate_path');
    expect($r['message'])->not->toContain('masih perlu banyak latihan');
});

test('FeedbackService: threshold boundary 69 is mid not high for SPEAKING specifically', function () {
    // Score 69 → mid for speaking specifically (< 70)
    $r = $this->service->generate(makeScores(69, 70, 70), 'intermediate_path');
    $speakingDetail = $r['details'][0]; // speaking is first
    expect($speakingDetail)->toContain('sudah') // mid
        ->not->toContain('kuat'); // not high
});

// ---- Output structure (Req 12.5) ----

test('FeedbackService: returns message, details, next_focus keys', function () {
    $r = $this->service->generate(makeScores(50, 50, 50), 'emergency_foundation');
    expect($r)->toHaveKeys(['message', 'details', 'next_focus']);
});

test('FeedbackService: details array contains exactly 4 elements', function () {
    $r = $this->service->generate(makeScores(50, 50, 50), 'emergency_foundation');
    expect($r['details'])->toHaveCount(4);
});

test('FeedbackService: message is non-empty string in Indonesian', function () {
    $r = $this->service->generate(makeScores(50, 50, 50), 'basic_speaking');
    expect($r['message'])->toBeString()->not->toBeEmpty();
    // Bahasa Indonesia check — all templates contain 'kamu' or 'fokus'
    expect(
        str_contains($r['message'], 'kamu') ||
        str_contains($r['message'], 'Kamu') ||
        str_contains($r['message'], 'fokus')
    )->toBeTrue();
});

test('FeedbackService: last details element contains next_focus phrase (Req 12.4)', function () {
    $r = $this->service->generate(makeScores(50, 50, 50), 'grammar_foundation');
    expect(end($r['details']))->toContain('Langkah berikutnya: fokus pada');
});

// ---- next_focus & fallback (Req 12.7) ----

test('FeedbackService: unknown path_key falls back to default (Req 12.7)', function () {
    $r = $this->service->generate(makeScores(50, 50, 50), 'nonexistent_path');
    expect($r['next_focus'])->toBe('pengembangan skill lebih lanjut');
});

test('FeedbackService: known path has specific next_focus', function () {
    $r = $this->service->generate(makeScores(85, 80, 80), 'industry_ready');
    expect($r['next_focus'])->not->toBe('pengembangan skill lebih lanjut');
});

// ---- Property 7: determinism ----

test('Property 7: identical inputs produce identical output', function () {
    $scores = makeScores(65, 70, 55);
    $a = $this->service->generate($scores, 'vocabulary_builder');
    $b = $this->service->generate($scores, 'vocabulary_builder');
    expect($a)->toBe($b);
})->group('property');

test('Property 7: all 8 path keys produce valid structure', function () {
    $pathKeys = [
        'emergency_foundation', 'fundamental_communication', 'basic_speaking',
        'grammar_foundation', 'vocabulary_builder', 'intermediate_path',
        'professional_simulation', 'industry_ready',
    ];
    foreach ($pathKeys as $key) {
        $r = $this->service->generate(makeScores(50, 50, 50), $key);
        expect($r)->toHaveKeys(['message', 'details', 'next_focus']);
        expect($r['details'])->toHaveCount(4);
        expect($r['message'])->toBeString()->not->toBeEmpty();
    }
})->group('property');

test('Property 7: threshold is deterministic across all score ranges', function () {
    // Verify threshold boundaries independently per skill
    $cases = [
        [59, 'low'], [60, 'mid'], [69, 'mid'], [70, 'high'], [100, 'high'],
    ];
    foreach ($cases as [$score, $expectedLevel]) {
        $r = $this->service->generate(makeScores($score, 60, 60), 'intermediate_path');
        $speakingDetail = $r['details'][0];
        if ($expectedLevel === 'low') {
            expect($speakingDetail)->toContain('masih perlu');
        } elseif ($expectedLevel === 'mid') {
            expect($speakingDetail)->toContain('sudah');
        } else {
            expect($speakingDetail)->toContain('kuat');
        }
    }
})->group('property');
