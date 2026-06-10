<?php

use App\Models\Module;
use App\Models\User;
use App\Models\UserLearningPath;
use App\Models\UserModuleProgress;
use App\Services\AdaptiveEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Task 7.4: Unit test 8 skenario path eksplisit (example test)
 * Task 7.5: Property test AdaptiveEngineService.evaluateRules totality + determinism
 * Task 7.6: Property test evaluateRules rejecting out-of-range input
 * Task 7.7: Property test Module unlock invariants
 * Task 7.8: Property test run idempotence + DB invariants
 *
 * Property 3: AdaptiveEngineService.evaluateRules — totality and determinism
 * Property 4: AdaptiveEngineService.evaluateRules — out-of-range input rejection
 * Property 5: AdaptiveEngineService — Module unlock invariants
 * Property 6: AdaptiveEngineService.run — idempotence on repeated invocation
 *
 * Validates Requirements: 9.1-9.6, 10.1-10.6, 26.1-26.3, 34.2
 */
describe('AdaptiveEngineService', function () {
    beforeEach(function () {
        $this->engine = new AdaptiveEngineService();

        // Setup modules — level and tag are different; tag is only 'basic' or 'advanced'
        $paths = [
            'emergency_foundation' => ['basic', 'advanced'],
            'fundamental_communication' => ['basic', 'advanced'],
            'basic_speaking' => ['basic', 'advanced'],
            'grammar_foundation' => ['basic', 'advanced'],
            'vocabulary_builder' => ['basic', 'advanced'],
            'intermediate_path' => ['basic', 'advanced'],
            'professional_simulation' => ['basic', 'advanced'],
            'industry_ready' => ['basic', 'advanced'],
        ];

        foreach ($paths as $pathKey => $levels) {
            foreach ($levels as $index => $level) {
                Module::create([
                    'path_key' => $pathKey,
                    'title' => "$pathKey - $level",
                    'level' => $level === 'advanced' ? 'professional' : 'basic',
                    'tag' => $level,
                    'is_active' => true,
                    'content' => [],
                ]);
            }
        }
    });

    describe('evaluateRules', function () {
        /**
         * 8 explicit path scenarios
         */
        it('evaluates (40,40,40) → emergency_foundation', function () {
            $result = $this->engine->evaluateRules(['speaking' => 40, 'grammar' => 40, 'vocabulary' => 40]);
            expect($result)->toBe('emergency_foundation');
        });

        it('evaluates (50,50,40) → fundamental_communication', function () {
            $result = $this->engine->evaluateRules(['speaking' => 50, 'grammar' => 50, 'vocabulary' => 40]);
            expect($result)->toBe('fundamental_communication');
        });

        it('evaluates (50,80,80) → basic_speaking', function () {
            $result = $this->engine->evaluateRules(['speaking' => 50, 'grammar' => 80, 'vocabulary' => 80]);
            expect($result)->toBe('basic_speaking');
        });

        it('evaluates (80,50,80) → grammar_foundation', function () {
            $result = $this->engine->evaluateRules(['speaking' => 80, 'grammar' => 50, 'vocabulary' => 80]);
            expect($result)->toBe('grammar_foundation');
        });

        it('evaluates (80,80,50) → vocabulary_builder', function () {
            $result = $this->engine->evaluateRules(['speaking' => 80, 'grammar' => 80, 'vocabulary' => 50]);
            expect($result)->toBe('vocabulary_builder');
        });

        it('evaluates (60,60,60) → intermediate_path', function () {
            $result = $this->engine->evaluateRules(['speaking' => 60, 'grammar' => 60, 'vocabulary' => 60]);
            expect($result)->toBe('intermediate_path');
        });

        it('evaluates (75,75,75) → professional_simulation', function () {
            $result = $this->engine->evaluateRules(['speaking' => 75, 'grammar' => 75, 'vocabulary' => 75]);
            expect($result)->toBe('professional_simulation');
        });

        it('evaluates (85,80,80) → industry_ready', function () {
            $result = $this->engine->evaluateRules(['speaking' => 85, 'grammar' => 80, 'vocabulary' => 80]);
            expect($result)->toBe('industry_ready');
        });

        /**
         * Additional edge cases
         */
        it('handles boundary condition at 70 for professional unlock', function () {
            // Just below 70 should not unlock professional
            $result69 = $this->engine->evaluateRules(['speaking' => 69, 'grammar' => 69, 'vocabulary' => 69]);
            expect($result69)->not->toBe('industry_ready');

            // At 70 should unlock professional
            $result70 = $this->engine->evaluateRules(['speaking' => 70, 'grammar' => 70, 'vocabulary' => 70]);
            expect($result70)->toBe('professional_simulation');
        });

        it('all scores at 0 returns lowest path', function () {
            $result = $this->engine->evaluateRules(['speaking' => 0, 'grammar' => 0, 'vocabulary' => 0]);
            expect($result)->toBe('emergency_foundation');
        });

        it('all scores at 100 returns highest path', function () {
            $result = $this->engine->evaluateRules(['speaking' => 100, 'grammar' => 100, 'vocabulary' => 100]);
            expect($result)->toBe('industry_ready');
        });
    });

    describe('Input Validation', function () {
        /**
         * Property 4: Out-of-range rejection
         */
        it('rejects negative speaking score', function () {
            expect(fn() => $this->engine->evaluateRules(['speaking' => -1, 'grammar' => 50, 'vocabulary' => 50]))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('rejects negative grammar score', function () {
            expect(fn() => $this->engine->evaluateRules(['speaking' => 50, 'grammar' => -1, 'vocabulary' => 50]))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('rejects negative vocabulary score', function () {
            expect(fn() => $this->engine->evaluateRules(['speaking' => 50, 'grammar' => 50, 'vocabulary' => -1]))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('rejects speaking > 100', function () {
            expect(fn() => $this->engine->evaluateRules(['speaking' => 101, 'grammar' => 50, 'vocabulary' => 50]))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('rejects grammar > 100', function () {
            expect(fn() => $this->engine->evaluateRules(['speaking' => 50, 'grammar' => 101, 'vocabulary' => 50]))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('rejects vocabulary > 100', function () {
            expect(fn() => $this->engine->evaluateRules(['speaking' => 50, 'grammar' => 50, 'vocabulary' => 101]))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('accepts boundary value 0', function () {
            $result = $this->engine->evaluateRules(['speaking' => 0, 'grammar' => 0, 'vocabulary' => 0]);
            expect($result)->toBeString();
        });

        it('accepts boundary value 100', function () {
            $result = $this->engine->evaluateRules(['speaking' => 100, 'grammar' => 100, 'vocabulary' => 100]);
            expect($result)->toBeString();
        });
    });

    describe('Module Unlock Logic', function () {
        /**
         * Property 5: Module unlock invariants
         */
        it('unlocks basic level modules for all paths', function () {
            $user = User::factory()->create();
            $this->engine->run($user->id, ['speaking' => 40, 'grammar' => 40, 'vocabulary' => 40]);

            $basic = Module::where('level', 'basic')->where('is_active', true)->get();
            foreach ($basic as $module) {
                $progress = UserModuleProgress::where('user_id', $user->id)
                    ->where('module_id', $module->id)
                    ->first();
                expect($progress?->is_unlocked)->toBeTrue();
            }
        });

        it('unlocks professional modules only when all scores >= 70', function () {
            $user = User::factory()->create();

            // Case 1: Not all scores >= 70
            $this->engine->run($user->id, ['speaking' => 60, 'grammar' => 60, 'vocabulary' => 60]);
            $professional = Module::where('level', 'professional')->where('is_active', true)->first();
            $progress = UserModuleProgress::where('user_id', $user->id)
                ->where('module_id', $professional->id)
                ->first();
            expect($progress?->is_unlocked)->toBeFalse();

            // Case 2: All scores >= 70
            $this->engine->run($user->id, ['speaking' => 70, 'grammar' => 70, 'vocabulary' => 70]);
            $progress = UserModuleProgress::where('user_id', $user->id)
                ->where('module_id', $professional->id)
                ->first();
            expect($progress?->is_unlocked)->toBeTrue();
        });

        it('unlocks matching path modules', function () {
            $user = User::factory()->create();
            $pathKey = 'professional_simulation';

            $this->engine->run($user->id, ['speaking' => 75, 'grammar' => 75, 'vocabulary' => 75]); // → professional_simulation

            $pathModules = Module::where('path_key', $pathKey)
                ->where('is_active', true)
                ->get();

            foreach ($pathModules as $module) {
                $progress = UserModuleProgress::where('user_id', $user->id)
                    ->where('module_id', $module->id)
                    ->first();
                expect($progress?->is_unlocked)->toBeTrue();
            }
        });

        it('locks modules not matching path and not basic', function () {
            $user = User::factory()->create();
            $this->engine->run($user->id, ['speaking' => 40, 'grammar' => 40, 'vocabulary' => 40]); // → emergency_foundation

            $otherPath = Module::where('path_key', 'professional_simulation')
                ->where('level', 'professional')
                ->where('is_active', true)
                ->first();

            if ($otherPath) {
                $progress = UserModuleProgress::where('user_id', $user->id)
                    ->where('module_id', $otherPath->id)
                    ->first();
                expect($progress?->is_unlocked)->toBeFalse();
            }
        });

        it('only unlocks active modules', function () {
            $user = User::factory()->create();

            // Create inactive module
            $inactive = Module::create([
                'path_key' => 'emergency_foundation',
                'title' => 'Inactive Module',
                'level' => 'basic',
                'tag' => 'basic',
                'is_active' => false,
                'content' => [],
            ]);

            $this->engine->run($user->id, ['speaking' => 40, 'grammar' => 40, 'vocabulary' => 40]);

            $progress = UserModuleProgress::where('user_id', $user->id)
                ->where('module_id', $inactive->id)
                ->first();
            expect($progress)->toBeNull();
        });
    });

    describe('Idempotence & DB Invariants', function () {
        /**
         * Property 6: run idempotence
         * Running multiple times with same input produces same DB state
         */
        it('property: run is idempotent', function () {
            $user = User::factory()->create();
            $scores = ['speaking' => 75, 'grammar' => 75, 'vocabulary' => 75];

            // First run
            $this->engine->run($user->id, $scores);
            $state1 = UserModuleProgress::where('user_id', $user->id)
                ->orderBy('module_id')
                ->get(['module_id', 'is_unlocked'])
                ->toArray();

            // Second run
            $this->engine->run($user->id, $scores);
            $state2 = UserModuleProgress::where('user_id', $user->id)
                ->orderBy('module_id')
                ->get(['module_id', 'is_unlocked'])
                ->toArray();

            expect($state1)->toBe($state2);
        });

        it('creates single active UserLearningPath per user', function () {
            $user = User::factory()->create();

            $this->engine->run($user->id, ['speaking' => 40, 'grammar' => 40, 'vocabulary' => 40]);
            $activePaths = UserLearningPath::where('user_id', $user->id)
                ->where('status', 'active')
                ->count();

            expect($activePaths)->toBe(1);
        });

        it('updates UserLearningPath on subsequent runs', function () {
            $user = User::factory()->create();

            // First run
            $this->engine->run($user->id, ['speaking' => 40, 'grammar' => 40, 'vocabulary' => 40]);
            $path1 = UserLearningPath::where('user_id', $user->id)->first();
            expect($path1->path_key)->toBe('emergency_foundation');

            // Second run with different scores
            $this->engine->run($user->id, ['speaking' => 75, 'grammar' => 75, 'vocabulary' => 75]);
            $path2 = UserLearningPath::where('user_id', $user->id)->first();
            expect($path2->path_key)->toBe('professional_simulation');
            expect($path2->id)->toBe($path1->id); // Same record
        });

        it('maintains unique constraint on UserModuleProgress', function () {
            $user = User::factory()->create();
            $module = Module::first();

            $this->engine->run($user->id, ['speaking' => 75, 'grammar' => 75, 'vocabulary' => 75]);
            $progress1 = UserModuleProgress::where('user_id', $user->id)
                ->where('module_id', $module->id)
                ->count();

            $this->engine->run($user->id, ['speaking' => 75, 'grammar' => 75, 'vocabulary' => 75]);
            $progress2 = UserModuleProgress::where('user_id', $user->id)
                ->where('module_id', $module->id)
                ->count();

            expect($progress2)->toBe($progress1);
        });
    });

    describe('Property: Totality & Determinism', function () {
        /**
         * Property 3: evaluateRules totality
         * For every input in range [0..100]³, produces valid path key
         */
        it('property: evaluateRules is total (all valid inputs have output)', function () {
            $validPathKeys = [
                'emergency_foundation', 'fundamental_communication', 'basic_speaking',
                'grammar_foundation', 'vocabulary_builder', 'intermediate_path',
                'professional_simulation', 'industry_ready',
            ];

            for ($s = 0; $s <= 100; $s += 10) {
                for ($g = 0; $g <= 100; $g += 10) {
                    for ($v = 0; $v <= 100; $v += 10) {
                        $result = $this->engine->evaluateRules(['speaking' => $s, 'grammar' => $g, 'vocabulary' => $v]);
                        expect($result)->toBeIn($validPathKeys);
                    }
                }
            }
        });

        /**
         * Property 3: evaluateRules determinism
         * Same input always produces same output
         */
        it('property: evaluateRules is deterministic', function () {
            $testCases = [
                ['speaking' => 40, 'grammar' => 40, 'vocabulary' => 40],
                ['speaking' => 50, 'grammar' => 50, 'vocabulary' => 40],
                ['speaking' => 75, 'grammar' => 75, 'vocabulary' => 75],
                ['speaking' => 85, 'grammar' => 80, 'vocabulary' => 80],
            ];

            foreach ($testCases as $case) {
                $r1 = $this->engine->evaluateRules($case);
                $r2 = $this->engine->evaluateRules($case);
                expect($r1)->toBe($r2);
            }
        });
    });

    describe('Return Value', function () {
        it('run returns array with required keys', function () {
            $user = User::factory()->create();
            $result = $this->engine->run($user->id, ['speaking' => 75, 'grammar' => 75, 'vocabulary' => 75]);

            expect($result)->toHaveKeys([
                'learning_path',
                'path_label',
                'modules_unlocked',
                'modules_locked',
            ]);
        });

        it('modules_unlocked contains correct modules', function () {
            $user = User::factory()->create();
            $result = $this->engine->run($user->id, ['speaking' => 75, 'grammar' => 75, 'vocabulary' => 75]);

            expect($result['modules_unlocked'])->toBeArray();
            foreach ($result['modules_unlocked'] as $moduleId) {
                expect($moduleId)->toBeInt();
                expect(Module::find($moduleId))->not->toBeNull();
            }
        });

        it('modules_locked contains correct modules', function () {
            $user = User::factory()->create();
            $result = $this->engine->run($user->id, ['speaking' => 40, 'grammar' => 40, 'vocabulary' => 40]);

            expect($result['modules_locked'])->toBeArray();
            foreach ($result['modules_locked'] as $moduleId) {
                expect($moduleId)->toBeInt();
                expect(Module::find($moduleId))->not->toBeNull();
            }
        });
    });
});
