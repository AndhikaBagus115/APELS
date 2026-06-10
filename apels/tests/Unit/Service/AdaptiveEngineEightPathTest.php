<?php

/**
 * Unit test: 8 path scenarios for AdaptiveEngineService::evaluateRules (Req 9.1, 34.1).
 *
 * Each test case covers one branch of the rule tree, from most-specific to most-general.
 */

use App\Services\AdaptiveEngineService;

beforeEach(function () {
    $this->engine = new AdaptiveEngineService();
});

// Rule 1: sp < 50 AND gr < 50 AND vo < 50 → emergency_foundation (Req 9.1.1)
test('emergency_foundation when all scores below 50', function () {
    expect($this->engine->evaluateRules(makeScores(40, 40, 40)))
        ->toBe('emergency_foundation');
});

test('emergency_foundation boundary: 49/49/49', function () {
    expect($this->engine->evaluateRules(makeScores(49, 49, 49)))
        ->toBe('emergency_foundation');
});

// Rule 2: sp < 60 AND vo < 60 → fundamental_communication (Req 9.1.2)
test('fundamental_communication when speaking and vocab below 60', function () {
    expect($this->engine->evaluateRules(makeScores(55, 80, 55)))
        ->toBe('fundamental_communication');
});

test('fundamental_communication not triggered when all below 50', function () {
    // sp < 50 AND gr < 50 AND vo < 50 takes priority
    expect($this->engine->evaluateRules(makeScores(45, 45, 45)))
        ->toBe('emergency_foundation');
});

// Rule 3: sp < 60 → basic_speaking (Req 9.1.3)
test('basic_speaking when only speaking below 60', function () {
    expect($this->engine->evaluateRules(makeScores(55, 80, 70)))
        ->toBe('basic_speaking');
});

// Rule 4: gr < 60 → grammar_foundation (Req 9.1.4)
test('grammar_foundation when only grammar below 60', function () {
    expect($this->engine->evaluateRules(makeScores(75, 50, 70)))
        ->toBe('grammar_foundation');
});

// Rule 5: vo < 60 → vocabulary_builder (Req 9.1.5)
test('vocabulary_builder when only vocabulary below 60', function () {
    expect($this->engine->evaluateRules(makeScores(75, 70, 50)))
        ->toBe('vocabulary_builder');
});

// Rule 6: sp >= 80 AND gr >= 75 AND vo >= 75 → industry_ready (Req 9.1.6)
test('industry_ready when all scores meet high threshold', function () {
    expect($this->engine->evaluateRules(makeScores(85, 80, 80)))
        ->toBe('industry_ready');
});

test('industry_ready boundary: exactly 80/75/75', function () {
    expect($this->engine->evaluateRules(makeScores(80, 75, 75)))
        ->toBe('industry_ready');
});

// Rule 7: sp >= 70 AND gr >= 70 → professional_simulation (Req 9.1.7)
test('professional_simulation when speaking and grammar at 70+', function () {
    expect($this->engine->evaluateRules(makeScores(75, 75, 75)))
        ->toBe('professional_simulation');
});

test('professional_simulation boundary: exactly 70/70/80', function () {
    expect($this->engine->evaluateRules(makeScores(70, 70, 80)))
        ->toBe('professional_simulation');
});

// Rule 8: default → intermediate_path (Req 9.1.8)
test('intermediate_path is default when no other rule matches', function () {
    expect($this->engine->evaluateRules(makeScores(65, 65, 65)))
        ->toBe('intermediate_path');
});

test('intermediate_path: sp=79 just below industry_ready', function () {
    // sp=79 < 80, so industry_ready is NOT triggered; sp>=70, gr>=70 → professional_simulation
    expect($this->engine->evaluateRules(makeScores(79, 76, 76)))
        ->toBe('professional_simulation');
});

test('intermediate_path: sp=69 just below professional_simulation threshold', function () {
    // sp=69 < 70, so professional_simulation is NOT triggered → intermediate
    expect($this->engine->evaluateRules(makeScores(69, 70, 70)))
        ->toBe('intermediate_path');
});

// Validation: out-of-range throws (Req 9.6)
test('throws InvalidArgumentException for score below 0', function () {
    expect(fn () => $this->engine->evaluateRules(makeScores(-1, 50, 50)))
        ->toThrow(InvalidArgumentException::class);
});

test('throws InvalidArgumentException for score above 100', function () {
    expect(fn () => $this->engine->evaluateRules(makeScores(50, 101, 50)))
        ->toThrow(InvalidArgumentException::class);
});

test('throws InvalidArgumentException for missing key', function () {
    expect(fn () => $this->engine->evaluateRules(['speaking' => 50, 'grammar' => 50]))
        ->toThrow(InvalidArgumentException::class);
});
