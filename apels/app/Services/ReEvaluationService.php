<?php

namespace App\Services;

use App\Models\DiagnosticResult;
use App\Models\UserModuleProgress;

/**
 * ReEvaluationService — determines if student should retake Diagnostic Test.
 *
 * Logic (Req 16.1, 16.3, 16.4):
 *   - shouldReEvaluateByProgress: true if completed/total >= 0.70 (total > 0)
 *   - canTakeTestToday: true if no DiagnosticResult today for user
 *
 * Dashboard shows "Kamu siap untuk tes ulang!" when both return true (Req 16.2).
 */
class ReEvaluationService
{
    /**
     * Check if student has completed >= 70% of their assigned modules.
     *
     * @param  int  $userId
     * @return bool
     */
    public function shouldReEvaluateByProgress(int $userId): bool
    {
        $total = UserModuleProgress::where('user_id', $userId)->count();

        if ($total === 0) {
            return false; // Req 16.3
        }

        $completed = UserModuleProgress::where('user_id', $userId)
            ->where('is_completed', true)
            ->count();

        return ($completed / $total) >= 0.70; // Req 16.1
    }

    /**
     * Check if student can take Diagnostic Test today (no test yet today).
     *
     * Uses application timezone via today() (Req 3.3, 16.4).
     *
     * @param  int  $userId
     * @return bool
     */
    public function canTakeTestToday(int $userId): bool
    {
        return !DiagnosticResult::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->exists();
    }
}
