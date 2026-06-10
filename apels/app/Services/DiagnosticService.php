<?php

namespace App\Services;

use App\Jobs\ProcessSpeakingAudio;
use App\Models\DiagnosticResult;
use App\Models\Feedback;
use Illuminate\Support\Facades\Log;

/**
 * DiagnosticService — orchestrator for Diagnostic Test submission flow.
 *
 * process() flow (Req 4.1-4.6):
 *   1. Call NlpAnalysisService (sync) → grammar + vocabulary scores
 *   2. Create DiagnosticResult record (speaking=0, is_speaking_processed=false)
 *   3. If audio → dispatch ProcessSpeakingAudio job (async)
 *   4. If no audio → run engine+feedback synchronously with speaking=0
 *   5. Return API response data
 *
 * runEngineAndFeedback() flow (Req 8, 9, 12.6):
 *   1. Calculate overall score via ScoringService
 *   2. Run AdaptiveEngineService (evaluate + persist)
 *   3. Generate feedback via FeedbackService
 *   4. Persist Feedback record
 *
 * Requirements: 4.1-4.6, 8, 9, 12.6, 27.2-27.3, 28.1-28.4
 */
class DiagnosticService
{
    public function __construct(
        private NlpAnalysisService $nlpService,
        private ScoringService $scoringService,
        private AdaptiveEngineService $adaptiveEngine,
        private FeedbackService $feedbackService,
    ) {}

    /**
     * Process a Diagnostic Test submission.
     *
     * @param  int    $userId
     * @param  array  $input  Keys: text_answers (array<string>), audio_path (string|null)
     * @return array{diagnostic_id: int, grammar: int, vocabulary: int, message: string}
     */
    public function process(int $userId, array $input): array
    {
        $textAnswers = $input['text_answers'] ?? [];
        $audioPath   = $input['audio_path'] ?? null;

        // 1. NLP analysis (sync) — fallback to 0 on failure (Req 4.6, 27.2)
        try {
            $nlpResult = $this->nlpService->analyze($textAnswers);
        } catch (\Throwable $e) {
            Log::error('DiagnosticService::process — NLP failed, using fallback', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            $nlpResult = ['grammar_score' => 0, 'vocab_score' => 0];
        }

        $grammar    = $nlpResult['grammar_score'];
        $vocabulary = $nlpResult['vocab_score'];

        // 2. Create DiagnosticResult (Req 4.2)
        $attempt = DiagnosticResult::where('user_id', $userId)->count() + 1;

        $diagnostic = DiagnosticResult::create([
            'user_id'               => $userId,
            'grammar'               => $grammar,
            'vocabulary'            => $vocabulary,
            'speaking'              => 0,
            'overall'               => 0,
            'attempt'               => $attempt,
            'audio_path'            => $audioPath,
            'is_speaking_processed' => false,
        ]);

        // 3. Dispatch async job or run sync (Req 4.3, 4.4)
        if ($audioPath) {
            ProcessSpeakingAudio::dispatch($userId, $audioPath, $diagnostic->id);
        } else {
            // No audio — run engine synchronously with speaking=0 (Req 4.4)
            $diagnostic->update(['is_speaking_processed' => true]);
            $this->runEngineAndFeedback($userId, $diagnostic->fresh());
        }

        // 4. Return response data (Req 4.5)
        return [
            'diagnostic_id' => $diagnostic->id,
            'grammar'       => $grammar,
            'vocabulary'    => $vocabulary,
            'message'       => $audioPath
                ? 'Diagnostic test berhasil dikirim. Hasil speaking sedang diproses.'
                : 'Diagnostic test berhasil diproses.',
        ];
    }

    /**
     * Run Adaptive Engine + Feedback after scores are final.
     *
     * Called by ProcessSpeakingAudio job (after Whisper) or synchronously
     * when no audio is submitted.
     *
     * @param  int               $userId
     * @param  DiagnosticResult  $diagnostic  Fresh instance with final scores.
     */
    public function runEngineAndFeedback(int $userId, DiagnosticResult $diagnostic): void
    {
        $scores = [
            'speaking'   => $diagnostic->speaking,
            'grammar'    => $diagnostic->grammar,
            'vocabulary' => $diagnostic->vocabulary,
        ];

        // 1. Calculate overall (Req 8)
        $overall = $this->scoringService->calculateOverall($scores);
        $diagnostic->update(['overall' => $overall]);

        // 2. Run Adaptive Engine (Req 9)
        $pathResult = $this->adaptiveEngine->run($userId, $scores);

        // 3. Generate feedback (Req 12)
        $feedback = $this->feedbackService->generate($scores, $pathResult['learning_path']);

        // 4. Persist Feedback record (Req 12.6)
        Feedback::create([
            'user_id'              => $userId,
            'diagnostic_result_id' => $diagnostic->id,
            'message'              => $feedback['message'],
            'details'              => $feedback['details'],
            'next_focus'           => $feedback['next_focus'],
        ]);
    }
}
