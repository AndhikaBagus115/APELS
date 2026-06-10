<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations — custom matchers for APELS domain
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidPathKey', function () {
    $validKeys = [
        'emergency_foundation', 'fundamental_communication', 'basic_speaking',
        'grammar_foundation', 'vocabulary_builder', 'intermediate_path',
        'professional_simulation', 'industry_ready',
    ];
    return $this->toBeIn($validKeys);
});

expect()->extend('toBeScore', function () {
    return $this->toBeInt()
        ->toBeGreaterThanOrEqual(0)
        ->toBeLessThanOrEqual(100);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function makeScores(int $speaking, int $grammar, int $vocabulary): array
{
    return compact('speaking', 'grammar', 'vocabulary');
}
