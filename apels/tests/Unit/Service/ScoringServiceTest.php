<?php

/**
 * Unit + Property tests for ScoringService (Req 8.1-8.5, 34.5).
 *
 * Property 1: calculateOverall — bounds [0..100], missing-key fallback, determinism.
 * Property 2: normalize — boundary correctness, monotonicity.
 */

use App\Services\ScoringService;

beforeEach(function () {
    $this->service = new ScoringService();
});

// ---- calculateOverall — example tests (Task 5.4) ----

test('calculateOverall: all 100 returns 100', function () {
    expect($this->service->calculateOverall(makeScores(100, 100, 100)))->toBe(100.0);
});

test('calculateOverall: all 0 returns 0', function () {
    expect($this->service->calculateOverall(makeScores(0, 0, 0)))->toBe(0.0);
});

test('calculateOverall: weighted formula', function () {
    $result = $this->service->calculateOverall(makeScores(80, 70, 60));
    $expected = round(80 * 0.40 + 70 * 0.30 + 60 * 0.30, 2);
    expect($result)->toBe($expected);
});

test('calculateOverall: missing key treated as 0 (Req 8.4)', function () {
    $result = $this->service->calculateOverall(['grammar' => 100, 'vocabulary' => 100]);
    $expected = round(0 * 0.40 + 100 * 0.30 + 100 * 0.30, 2);
    expect($result)->toBe($expected);
});

test('calculateOverall: throws on out-of-range score', function () {
    expect(fn () => $this->service->calculateOverall(makeScores(101, 50, 50)))
        ->toThrow(InvalidArgumentException::class);
});

test('calculateOverall: throws on non-numeric score', function () {
    expect(fn () => $this->service->calculateOverall(['speaking' => 'abc', 'grammar' => 50, 'vocabulary' => 50]))
        ->toThrow(InvalidArgumentException::class);
});

// ---- Property 1: bounds + determinism ----

test('Property 1: calculateOverall always returns value in [0, 100]', function () {
    // Spot-check a grid of representative values
    $samples = [0, 25, 50, 60, 70, 75, 80, 100];
    foreach ($samples as $sp) {
        foreach ($samples as $gr) {
            foreach ($samples as $vo) {
                $result = $this->service->calculateOverall(makeScores($sp, $gr, $vo));
                expect($result)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(100.0);
            }
        }
    }
})->group('property');

test('Property 1: calculateOverall is deterministic for identical inputs', function () {
    $scores = makeScores(70, 65, 80);
    $a = $this->service->calculateOverall($scores);
    $b = $this->service->calculateOverall($scores);
    expect($a)->toBe($b);
})->group('property');

// ---- normalize — example tests ----

test('normalize: min boundary returns 0', function () {
    expect($this->service->normalize(0.0, 0.0, 1.0))->toBe(0);
});

test('normalize: max boundary returns 100', function () {
    expect($this->service->normalize(1.0, 0.0, 1.0))->toBe(100);
});

test('normalize: midpoint returns 50', function () {
    expect($this->service->normalize(0.5, 0.0, 1.0))->toBe(50);
});

test('normalize: works with custom range', function () {
    expect($this->service->normalize(50.0, 0.0, 100.0))->toBe(50);
});

test('normalize: throws when min >= max', function () {
    expect(fn () => $this->service->normalize(0.5, 1.0, 0.0))
        ->toThrow(InvalidArgumentException::class);
});

test('normalize: throws when score out of [min, max]', function () {
    expect(fn () => $this->service->normalize(2.0, 0.0, 1.0))
        ->toThrow(InvalidArgumentException::class);
});

// ---- Property 2: boundary correctness + monotonicity ----

test('Property 2: normalize always returns value in [0, 100]', function () {
    $samples = [0.0, 0.1, 0.25, 0.5, 0.75, 0.9, 1.0];
    foreach ($samples as $score) {
        $result = $this->service->normalize($score, 0.0, 1.0);
        expect($result)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    }
})->group('property');

test('Property 2: normalize is monotonically non-decreasing', function () {
    $scores = [0.0, 0.2, 0.4, 0.6, 0.8, 1.0];
    $results = array_map(fn ($s) => $this->service->normalize($s, 0.0, 1.0), $scores);
    for ($i = 1; $i < count($results); $i++) {
        expect($results[$i])->toBeGreaterThanOrEqual($results[$i - 1]);
    }
})->group('property');
