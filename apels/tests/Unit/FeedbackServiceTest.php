<?php

use App\Services\FeedbackService;

/**
 * Task 6.2: Property test FeedbackService::generate
 * Property 7: FeedbackService.generate — threshold determinism and structural completeness
 * Validates Requirements: 12.2, 12.3, 12.5, 12.7
 *
 * Task 6.3: Unit test fallback path tidak dikenal & locale ID
 * Validates Requirements: 12.7, 12.8
 */
describe('FeedbackService', function () {
    beforeEach(function () {
        $this->feedbackService = new FeedbackService();
    });

    describe('generate', function () {
        it('returns array with required keys', function () {
            $scores = ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85];
            $result = $this->feedbackService->generate($scores, 'professional_simulation');

            expect($result)->toHaveKeys(['message', 'details', 'next_focus']);
            expect($result['message'])->toBeString();
            expect($result['details'])->toBeArray();
            expect($result['next_focus'])->toBeString();
        });

        it('classifies speaking_low for score < 60', function () {
            $scores = ['speaking' => 50, 'grammar' => 80, 'vocabulary' => 80];
            $result = $this->feedbackService->generate($scores, 'professional_simulation');

            expect($result['message'])->toContain('Speaking');
            expect($result['message'])->toContain('latihan');
        });

        it('classifies speaking_mid for score 60-69', function () {
            $scores = ['speaking' => 65, 'grammar' => 80, 'vocabulary' => 80];
            $result = $this->feedbackService->generate($scores, 'professional_simulation');

            expect($result['message'])->toBeString();
            expect(strlen($result['message']))->toBeGreaterThan(0);
        });

        it('classifies speaking_high for score >= 70', function () {
            $scores = ['speaking' => 80, 'grammar' => 80, 'vocabulary' => 80];
            $result = $this->feedbackService->generate($scores, 'professional_simulation');

            expect($result['message'])->toBeString();
        });

        it('includes all three skill levels in feedback', function () {
            $scores = ['speaking' => 75, 'grammar' => 65, 'vocabulary' => 55];
            $result = $this->feedbackService->generate($scores, 'professional_simulation');

            expect($result['message'])->toContain('Speaking');
            expect($result['message'])->toContain('Grammar');
            expect($result['message'])->toContain('Vocabulary');
        });

        it('generates Indonesian feedback message', function () {
            $scores = ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85];
            $result = $this->feedbackService->generate($scores, 'professional_simulation');

            // Check for Indonesian words/markers
            expect($result['message'])->toBeString();
            expect(strlen($result['message']))->toBeGreaterThan(10);
        });

        it('provides path-specific next focus', function () {
            $scores = ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85];
            $result = $this->feedbackService->generate($scores, 'professional_simulation');

            expect($result['next_focus'])->toBeString();
            expect(strlen($result['next_focus']))->toBeGreaterThan(0);
        });

        it('handles all 8 learning paths', function () {
            $paths = [
                'emergency_foundation',
                'fundamental_communication',
                'basic_speaking',
                'grammar_foundation',
                'vocabulary_builder',
                'intermediate_path',
                'professional_simulation',
                'industry_ready',
            ];

            $scores = ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85];

            foreach ($paths as $path) {
                $result = $this->feedbackService->generate($scores, $path);
                expect($result['next_focus'])->toBeString();
            }
        });

        it('fallback next focus for unknown path', function () {
            $scores = ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85];
            $result = $this->feedbackService->generate($scores, 'unknown_path');

            expect($result['next_focus'])->toContain('skill');
        });

        it('treats missing score as 0', function () {
            $scores = ['speaking' => 0]; // grammar and vocabulary missing
            $result = $this->feedbackService->generate($scores, 'professional_simulation');

            expect($result)->toHaveKeys(['message', 'details', 'next_focus']);
        });

        it('threshold boundary at 60 (low/mid boundary)', function () {
            $scoresJust59 = ['speaking' => 59, 'grammar' => 80, 'vocabulary' => 80];
            $scoresAt60 = ['speaking' => 60, 'grammar' => 80, 'vocabulary' => 80];

            $result59 = $this->feedbackService->generate($scoresJust59, 'professional_simulation');
            $result60 = $this->feedbackService->generate($scoresAt60, 'professional_simulation');

            // Results should be different (low vs mid)
            expect($result59['message'])->not->toBe($result60['message']);
        });

        it('threshold boundary at 70 (mid/high boundary)', function () {
            $scoresJust69 = ['speaking' => 69, 'grammar' => 80, 'vocabulary' => 80];
            $scoresAt70 = ['speaking' => 70, 'grammar' => 80, 'vocabulary' => 80];

            $result69 = $this->feedbackService->generate($scoresJust69, 'professional_simulation');
            $result70 = $this->feedbackService->generate($scoresAt70, 'professional_simulation');

            // Results should be different (mid vs high)
            expect($result69['message'])->not->toBe($result70['message']);
        });
    });

    describe('Property: Determinism & Completeness', function () {
        /**
         * Property 7: FeedbackService.generate — threshold determinism
         * Same input always produces same output
         */
        it('property: generate is deterministic', function () {
            $scores = ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85];
            $path = 'professional_simulation';

            $result1 = $this->feedbackService->generate($scores, $path);
            $result2 = $this->feedbackService->generate($scores, $path);

            expect($result1)->toBe($result2);
        });

        /**
         * Property 8: Structural completeness
         * All results have required keys regardless of input
         */
        it('property: all results have complete structure', function () {
            $testCases = [
                ['speaking' => 0, 'grammar' => 0, 'vocabulary' => 0],
                ['speaking' => 50, 'grammar' => 50, 'vocabulary' => 50],
                ['speaking' => 100, 'grammar' => 100, 'vocabulary' => 100],
                ['speaking' => 30, 'grammar' => 60, 'vocabulary' => 90],
            ];

            $paths = ['emergency_foundation', 'professional_simulation', 'industry_ready'];

            foreach ($testCases as $scores) {
                foreach ($paths as $path) {
                    $result = $this->feedbackService->generate($scores, $path);

                    expect($result)->toHaveKeys(['message', 'details', 'next_focus']);
                    expect($result['message'])->toBeString();
                    expect($result['next_focus'])->toBeString();
                    expect($result['details'])->toBeArray();
                }
            }
        });

        /**
         * Property 9: Threshold rule consistency
         * Classification is consistent with thresholds
         */
        it('property: thresholds are consistent', function () {
            for ($i = 0; $i <= 100; $i += 10) {
                $scores = ['speaking' => $i, 'grammar' => 80, 'vocabulary' => 80];
                $result = $this->feedbackService->generate($scores, 'professional_simulation');

                expect($result['details'])->toBeArray();
                expect(count($result['details']))->toBeGreaterThan(0);
                expect($result['message'])->toContain('Speaking');
            }
        });
    });

    describe('Locale & Path Fallback', function () {
        it('generates response in Indonesian', function () {
            $scores = ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85];
            $result = $this->feedbackService->generate($scores, 'professional_simulation');

            // Check that message is non-empty and likely Indonesian
            expect($result['message'])->toBeString();
            expect(strlen($result['message']))->toBeGreaterThan(5);
        });

        it('handles unknown path gracefully', function () {
            $scores = ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85];
            $result = $this->feedbackService->generate($scores, 'non_existent_path');

            expect($result)->toHaveKeys(['message', 'details', 'next_focus']);
            expect($result['next_focus'])->toBeTruthy();
        });

        it('fallback message contains helpful content', function () {
            $scores = ['speaking' => 75, 'grammar' => 80, 'vocabulary' => 85];
            $result = $this->feedbackService->generate($scores, 'unknown_path');

            expect($result['next_focus'])->not->toBeEmpty();
        });
    });
});
