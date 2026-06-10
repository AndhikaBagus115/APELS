<?php

namespace App\Services;

use App\Models\Module;
use App\Models\UserLearningPath;
use App\Models\UserModuleProgress;
use InvalidArgumentException;

/**
 * Adaptive Engine — rule-based learning path mapper + persistence.
 *
 * Core methods:
 *   - evaluateRules(scores): string — pure, no DB (PBT target)
 *   - shouldUnlock(module, pathKey, scores): bool — pure decision
 *   - resolveModules(pathKey, scores): array — reads DB (modules)
 *   - run(userId, scores): array — orchestrator: evaluate + resolve + persist
 *   - persist(userId, pathKey, modules): void — writes DB
 *
 * Requirements: 9.1-9.6, 10.1-10.6, 11.3, 26.1-26.3
 */
class AdaptiveEngineService
{
    /**
     * Full orchestrator: evaluate rules → resolve modules → persist state.
     *
     * Returns shape (Req 9.5):
     *   ['learning_path' => string, 'path_label' => string,
     *    'modules_unlocked' => list<int>, 'modules_locked' => list<int>]
     *
     * Idempotent: calling twice with same scores + same module state
     * produces identical output and DB state (Req 26.1).
     *
     * @param  int                          $userId
     * @param  array<string, int|float>     $scores  Keys: speaking, grammar, vocabulary (0..100).
     * @return array{learning_path: string, path_label: string, modules_unlocked: list<int>, modules_locked: list<int>}
     */
    public function run(int $userId, array $scores): array
    {
        $pathKey = $this->evaluateRules($scores);
        $modules = $this->resolveModules($pathKey, $scores);

        $this->persist($userId, $pathKey, $modules);

        $label = config("learning_paths.{$pathKey}.label", $pathKey);

        return [
            'learning_path'    => $pathKey,
            'path_label'       => $label,
            'modules_unlocked' => $modules['unlocked'],
            'modules_locked'   => $modules['locked'],
        ];
    }

    /**
     * Persist learning path + module progress to DB.
     *
     * - UserLearningPath: updateOrCreate single active record per user (Req 26.2).
     * - UserModuleProgress: updateOrCreate per (user_id, module_id) pair (Req 26.3).
     *
     * @param  int    $userId
     * @param  string $pathKey
     * @param  array{unlocked: list<int>, locked: list<int>} $modules
     */
    private function persist(int $userId, string $pathKey, array $modules): void
    {
        // Single active learning path per user (Req 26.2)
        UserLearningPath::updateOrCreate(
            ['user_id' => $userId],
            [
                'path_key'    => $pathKey,
                'status'      => 'active',
                'assigned_at' => now(),
            ]
        );

        // Module progress — unique per (user_id, module_id) (Req 26.3)
        foreach ($modules['unlocked'] as $moduleId) {
            UserModuleProgress::updateOrCreate(
                ['user_id' => $userId, 'module_id' => $moduleId],
                ['is_unlocked' => true]
            );
        }

        foreach ($modules['locked'] as $moduleId) {
            UserModuleProgress::updateOrCreate(
                ['user_id' => $userId, 'module_id' => $moduleId],
                ['is_unlocked' => false]
            );
        }
    }

    /**
     * Map a triple of normalized scores to a learning path key.
     *
     * @param  array<string, int|float>  $scores Keys: speaking, grammar, vocabulary; range 0..100 inclusive.
     * @return string                            One of 8 valid path keys (Req 9.3).
     *
     * @throws InvalidArgumentException When any score is missing, non-numeric, or outside 0..100.
     */
    public function evaluateRules(array $scores): string
    {
        $speaking   = $this->validateScore($scores, 'speaking');
        $grammar    = $this->validateScore($scores, 'grammar');
        $vocabulary = $this->validateScore($scores, 'vocabulary');

        // Urutan branching paling spesifik ke paling umum (Req 9.1).
        if ($speaking < 50 && $grammar < 50 && $vocabulary < 50) {
            return 'emergency_foundation';
        }
        if ($speaking < 60 && $vocabulary < 60) {
            return 'fundamental_communication';
        }
        if ($speaking < 60) {
            return 'basic_speaking';
        }
        if ($grammar < 60) {
            return 'grammar_foundation';
        }
        if ($vocabulary < 60) {
            return 'vocabulary_builder';
        }
        if ($speaking >= 80 && $grammar >= 75 && $vocabulary >= 75) {
            return 'industry_ready';
        }
        if ($speaking >= 70 && $grammar >= 70) {
            return 'professional_simulation';
        }
        return 'intermediate_path';
    }

    /**
     * Validate that a score key exists, is numeric, and lies within 0..100 inclusive.
     */
    private function validateScore(array $scores, string $key): int|float
    {
        if (!array_key_exists($key, $scores)) {
            throw new InvalidArgumentException("Missing score key: {$key}");
        }
        $value = $scores[$key];
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Score for {$key} must be numeric, got " . gettype($value));
        }
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException("Score for {$key} must be within 0..100, got {$value}");
        }
        return $value;
    }

    /**
     * Resolve daftar Module yang harus di-unlock vs locked.
     *
     * Hanya memproses Module dengan `is_active = true` (Req 10.6).
     *
     * @param  string                       $pathKey
     * @param  array<string, int|float>     $scores
     * @return array{unlocked: list<int>, locked: list<int>}
     */
    public function resolveModules(string $pathKey, array $scores): array
    {
        $unlocked = [];
        $locked = [];

        $modules = Module::where('is_active', true)->get();

        foreach ($modules as $module) {
            if ($this->shouldUnlock($module, $pathKey, $scores)) {
                $unlocked[] = $module->id;
            } else {
                $locked[] = $module->id;
            }
        }

        return ['unlocked' => $unlocked, 'locked' => $locked];
    }

    /**
     * Pure decision: apakah satu module harus di-unlock.
     *
     * Rules (Req 10.1-10.4):
     *   1. level == 'basic' → true
     *   2. level == 'professional' → true iff all scores >= 70
     *   3. module.path_key == pathKey → true
     *   4. else → false
     */
    public function shouldUnlock(Module $module, string $pathKey, array $scores): bool
    {
        if ($module->level === 'basic') {
            return true;
        }

        if ($module->level === 'professional') {
            $sp = $scores['speaking']   ?? 0;
            $gr = $scores['grammar']    ?? 0;
            $vo = $scores['vocabulary'] ?? 0;
            return $sp >= 70 && $gr >= 70 && $vo >= 70;
        }

        return $module->path_key === $pathKey;
    }
}
