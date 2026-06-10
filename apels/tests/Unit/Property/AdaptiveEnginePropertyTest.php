<?php

/**
 * Property-Based Tests for AdaptiveEngineService::evaluateRules (Req 9.2, 9.3, 9.6, 34.2).
 *
 * Property 3: totality — ALWAYS returns one of 8 valid path keys.
 * Property 4: determinism — same input → same output.
 * Property 4 rejection: out-of-range → InvalidArgumentException.
 *
 * Uses grid sampling as lightweight PBT (Eris not yet configured in this test).
 * Full PBT with Eris generators can be added later.
 */

use App\Services\AdaptiveEngineService;

beforeEach(function () {
    $this->engine = new AdaptiveEngineService();
});

$validPathKeys = [
    'emergency_foundation', 'fundamental_communication', 'basic_speaking',
    'grammar_foundation', 'vocabulary_builder', 'intermediate_path',
    'professional_simulation', 'industry_ready',
];

// ---- Property 3: Totality (Req 9.3, 34.2) ----

test('Property 3: evaluateRules returns a valid path key for all score combinations (grid sample)', function () use ($validPathKeys) {
    // Grid of boundary and representative values — 7^3 = 343 combinations
    $samples = [0, 1, 49, 50, 59, 60, 69, 70, 75, 79, 80, 99, 100];
    foreach ($samples as $sp) {
        foreach ($samples as $gr) {
            foreach ($samples as $vo) {
                $result = $this->engine->evaluateRules(makeScores($sp, $gr, $vo));
                expect($result)->toBeIn($validPathKeys,
                    "evaluateRules({$sp},{$gr},{$vo}) returned invalid key: {$result}");
            }
        }
    }
})->group('property');

// ---- Property 4: Determinism / Idempotence (Req 9.2) ----

test('Property 4: evaluateRules is deterministic for identical inputs', function () {
    $samples = [
        makeScores(40, 40, 40),
        makeScores(60, 60, 60),
        makeScores(75, 75, 75),
        makeScores(85, 80, 80),
        makeScores(0, 0, 0),
        makeScores(100, 100, 100),
    ];
    foreach ($samples as $scores) {
        $a = $this->engine->evaluateRules($scores);
        $b = $this->engine->evaluateRules($scores);
        expect($a)->toBe($b);
    }
})->group('property');

// ---- Property 4 + Req 9.6: Out-of-range rejection ----

test('Property 4: throws for score < 0', function () {
    foreach ([['speaking', -1], ['grammar', -1], ['vocabulary', -1]] as [$key, $val]) {
        $scores = makeScores(50, 50, 50);
        $scores[$key] = $val;
        expect(fn () => $this->engine->evaluateRules($scores))->toThrow(InvalidArgumentException::class);
    }
})->group('property');

test('Property 4: throws for score > 100', function () {
    foreach ([['speaking', 101], ['grammar', 200], ['vocabulary', 999]] as [$key, $val]) {
        $scores = makeScores(50, 50, 50);
        $scores[$key] = $val;
        expect(fn () => $this->engine->evaluateRules($scores))->toThrow(InvalidArgumentException::class);
    }
})->group('property');

test('Property 4: throws for non-numeric score', function () {
    expect(fn () => $this->engine->evaluateRules(['speaking' => 'high', 'grammar' => 50, 'vocabulary' => 50]))
        ->toThrow(InvalidArgumentException::class);
})->group('property');

test('Property 4: boundary 0 is valid (not rejected)', function () use ($validPathKeys) {
    expect($this->engine->evaluateRules(makeScores(0, 0, 0)))->toBeIn($validPathKeys);
})->group('property');

test('Property 4: boundary 100 is valid (not rejected)', function () use ($validPathKeys) {
    expect($this->engine->evaluateRules(makeScores(100, 100, 100)))->toBeIn($validPathKeys);
})->group('property');
