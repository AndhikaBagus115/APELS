<?php

use App\Services\ScoringService;

/**
 * Task 5.2: Property test ScoringService::calculateOverall
 * Property 1: ScoringService.calculateOverall — bounds, missing-key fallback, determinism
 * Validates Requirements: 8.2, 8.3, 8.4
 *
 * Task 5.3: Property test ScoringService::normalize
 * Property 2: ScoringService.normalize — boundary correctness and monotonicity
 * Validates Requirements: 8.5
 *
 * Task 5.4: Unit test ScoringService (smoke + edge cases)
 * Validates Requirements: 8.1, 8.2
 */
describe('ScoringService', function () {
    beforeEach(function () {
        $this->scoringService = new ScoringService();
    });

    describe('calculateOverall', function () {
        it('calculates weighted average of scores (0.40 speaking, 0.30 grammar, 0.30 vocabulary)', function () {
            // (100 * 0.40 + 80 * 0.30 + 90 * 0.30) = 40 + 24 + 27 = 91
            $result = $this->scoringService->calculateOverall(['speaking' => 100, 'grammar' => 80, 'vocabulary' => 90]);
            expect($result)->toBe(91.0);
        });

        it('handles all zeros correctly', function () {
            $result = $this->scoringService->calculateOverall(['speaking' => 0, 'grammar' => 0, 'vocabulary' => 0]);
            expect($result)->toBe(0.0);
        });

        it('handles all 100s correctly', function () {
            $result = $this->scoringService->calculateOverall(['speaking' => 100, 'grammar' => 100, 'vocabulary' => 100]);
            expect($result)->toBe(100.0);
        });

        it('rounds to 2 decimal places', function () {
            // (50 * 0.40 + 50 * 0.30 + 50 * 0.30) = 20 + 15 + 15 = 50.00
            $result = $this->scoringService->calculateOverall(['speaking' => 50, 'grammar' => 50, 'vocabulary' => 50]);
            expect($result)->toBe(50.0);

            // Test case with rounding: (85 * 0.40 + 75 * 0.30 + 70 * 0.30) = 34 + 22.5 + 21 = 77.5
            $result = $this->scoringService->calculateOverall(['speaking' => 85, 'grammar' => 75, 'vocabulary' => 70]);
            expect($result)->toBe(77.5);
        });

        it('is deterministic (same input produces same output)', function () {
            $scores = ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85];
            $result1 = $this->scoringService->calculateOverall($scores);
            $result2 = $this->scoringService->calculateOverall($scores);
            expect($result1)->toBe($result2);
        });

        it('treats missing keys as 0', function () {
            // When called with array, missing keys default to 0
            // (100 * 0.40 + 0 * 0.30 + 0 * 0.30) = 40
            $result = $this->scoringService->calculateOverall(['speaking' => 100]);
            expect($result)->toBe(40.0);
        });

        it('respects weight distribution (speaking > grammar = vocabulary)', function () {
            // Test that speaking has highest weight
            $result1 = $this->scoringService->calculateOverall(['speaking' => 100, 'grammar' => 0, 'vocabulary' => 0]);   // 40
            $result2 = $this->scoringService->calculateOverall(['speaking' => 0, 'grammar' => 100, 'vocabulary' => 0]);   // 30
            $result3 = $this->scoringService->calculateOverall(['speaking' => 0, 'grammar' => 0, 'vocabulary' => 100]);   // 30

            expect($result1)->toBeGreaterThan($result2);
            expect($result2)->toBe($result3); // grammar and vocabulary have same weight
        });

        it('maintains order (higher input score = higher output)', function () {
            $low = $this->scoringService->calculateOverall(['speaking' => 30, 'grammar' => 40, 'vocabulary' => 50]);
            $mid = $this->scoringService->calculateOverall(['speaking' => 50, 'grammar' => 60, 'vocabulary' => 70]);
            $high = $this->scoringService->calculateOverall(['speaking' => 80, 'grammar' => 90, 'vocabulary' => 100]);

            expect($low)->toBeLessThan($mid);
            expect($mid)->toBeLessThan($high);
        });

        it('rejects negative scores (out of range)', function () {
            expect(fn() => $this->scoringService->calculateOverall(['speaking' => -10, 'grammar' => 50, 'vocabulary' => 50]))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('rejects scores > 100 (out of range)', function () {
            expect(fn() => $this->scoringService->calculateOverall(['speaking' => 150, 'grammar' => 50, 'vocabulary' => 50]))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('accepts boundary values 0 and 100', function () {
            expect($this->scoringService->calculateOverall(['speaking' => 0, 'grammar' => 0, 'vocabulary' => 0]))->toBe(0.0);
            expect($this->scoringService->calculateOverall(['speaking' => 100, 'grammar' => 100, 'vocabulary' => 100]))->toBe(100.0);
        });
    });

    describe('normalize', function () {
        it('normalizes score to 0-100 scale', function () {
            // (0.5 - 0) / (1 - 0) * 100 = 50
            $result = $this->scoringService->normalize(0.5, 0, 1);
            expect($result)->toBe(50);
        });

        it('returns 0 at lower boundary', function () {
            $result = $this->scoringService->normalize(0, 0, 100);
            expect($result)->toBe(0);
        });

        it('returns 100 at upper boundary', function () {
            $result = $this->scoringService->normalize(100, 0, 100);
            expect($result)->toBe(100);
        });

        it('uses default min=0 max=1 when not specified', function () {
            // Default: (0.5 - 0) / (1 - 0) * 100 = 50
            $result = $this->scoringService->normalize(0.5);
            expect($result)->toBe(50);
        });

        it('rounds result to integer', function () {
            // (0.337 - 0) / (1 - 0) * 100 = 33.7 → rounds to 34
            $result = $this->scoringService->normalize(0.337, 0, 1);
            expect($result)->toBeInt();
        });

        it('is monotonic (higher input = higher output)', function () {
            $low = $this->scoringService->normalize(0.25, 0, 1);
            $mid = $this->scoringService->normalize(0.5, 0, 1);
            $high = $this->scoringService->normalize(0.75, 0, 1);

            expect($low)->toBeLessThan($mid);
            expect($mid)->toBeLessThan($high);
        });

        it('handles custom min/max range correctly', function () {
            // (50 - 0) / (100 - 0) * 100 = 50
            $result = $this->scoringService->normalize(50, 0, 100);
            expect($result)->toBe(50);

            // (1 - 0) / (2 - 0) * 100 = 50
            $result = $this->scoringService->normalize(1, 0, 2);
            expect($result)->toBe(50);
        });

        it('returns integer type', function () {
            $result = $this->scoringService->normalize(0.5, 0, 1);
            expect($result)->toBeInt();
        });

        it('handles zero denominator gracefully', function () {
            // min == max should throw InvalidArgumentException
            expect(fn() => $this->scoringService->normalize(50, 50, 50))
                ->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('Property: Bounds & Validity', function () {
        /**
         * Property 1: calculateOverall bounds
         * For any valid input (0-100 each), output is always 0-100
         */
        it('property: calculateOverall output is always within bounds [0,100]', function () {
            for ($i = 0; $i < 100; $i++) {
                $s = rand(0, 100);
                $g = rand(0, 100);
                $v = rand(0, 100);

                $result = $this->scoringService->calculateOverall(['speaking' => $s, 'grammar' => $g, 'vocabulary' => $v]);
                expect($result)->toBeGreaterThanOrEqual(0);
                expect($result)->toBeLessThanOrEqual(100);
            }
        });

        /**
         * Property 2: normalize output is always 0-100
         */
        it('property: normalize output is always within bounds [0,100]', function () {
            for ($i = 0; $i < 100; $i++) {
                $min = (float) rand(0, 50);
                $max = $min + (float) rand(1, 50);
                $value = $min + (($max - $min) * (rand(0, 100) / 100)); // Ensure value is within [min, max]

                $result = $this->scoringService->normalize($value, $min, $max);
                expect($result)->toBeGreaterThanOrEqual(0);
                expect($result)->toBeLessThanOrEqual(100);
            }
        });

        /**
         * Property 3: calculateOverall determinism
         * Same input always produces same output
         */
        it('property: calculateOverall is deterministic', function () {
            $testCases = [
                ['speaking' => 0, 'grammar' => 0, 'vocabulary' => 0],
                ['speaking' => 100, 'grammar' => 100, 'vocabulary' => 100],
                ['speaking' => 50, 'grammar' => 50, 'vocabulary' => 50],
                ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85],
            ];

            foreach ($testCases as $case) {
                $r1 = $this->scoringService->calculateOverall($case);
                $r2 = $this->scoringService->calculateOverall($case);
                expect($r1)->toBe($r2);
            }
        });
    });
});
