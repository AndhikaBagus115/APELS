<?php

namespace App\Jobs;

use App\Models\DiagnosticResult;
use App\Services\DiagnosticService;
use App\Services\SpeechToTextService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ProcessSpeakingAudio — async queue job for Whisper processing.
 *
 * Flow (Req 6.4-6.9, 23.2-23.3, 27.4, 31.3):
 *   1. Call SpeechToTextService::analyze(audioPath) → speaking score
 *   2. Update DiagnosticResult: speaking = score, is_speaking_processed = true
 *   3. Call DiagnosticService::runEngineAndFeedback(userId, diagnostic)
 *   4. Delete audio file from private storage
 *
 * Retry: max 3 attempts, 5s backoff (Req 6.8)
 * Failed: set speaking=0, is_speaking_processed=true, delete audio, log (Req 6.9, 23.3)
 */
class ProcessSpeakingAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;
    public int $timeout = 120; // generous timeout for Whisper

    public function __construct(
        public int $userId,
        public string $audioPath,
        public int $diagnosticId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SpeechToTextService $stt, DiagnosticService $diagnosticService): void
    {
        // 1. Get speaking score from Whisper
        $score = $stt->analyze($this->audioPath);

        // 2. Update DiagnosticResult (Req 6.4)
        $diagnostic = DiagnosticResult::findOrFail($this->diagnosticId);
        $diagnostic->update([
            'speaking'               => $score,
            'is_speaking_processed'  => true,
        ]);

        // 3. Run Adaptive Engine + Feedback (Req 6.5)
        $diagnosticService->runEngineAndFeedback($this->userId, $diagnostic->fresh());

        // 4. Delete audio file (Req 6.6, 23.2)
        $this->deleteAudio();
    }

    /**
     * Handle job failure after all retries exhausted (Req 6.9, 23.3).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessSpeakingAudio failed — all retries exhausted', [
            'user_id'       => $this->userId,
            'diagnostic_id' => $this->diagnosticId,
            'audio_path'    => $this->audioPath,
            'error'         => $exception->getMessage(),
        ]);

        // Set speaking=0, mark as processed so UI doesn't hang
        DiagnosticResult::where('id', $this->diagnosticId)->update([
            'speaking'              => 0,
            'is_speaking_processed' => true,
        ]);

        // Still run engine with score 0 so student gets a path
        try {
            $diagnostic = DiagnosticResult::find($this->diagnosticId);
            if ($diagnostic) {
                app(DiagnosticService::class)->runEngineAndFeedback($this->userId, $diagnostic);
            }
        } catch (\Throwable $e) {
            Log::error('ProcessSpeakingAudio::failed — engine fallback also failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Delete audio file (Req 23.3)
        $this->deleteAudio();
    }

    /**
     * Delete audio file from private storage.
     */
    private function deleteAudio(): void
    {
        try {
            if (Storage::disk('private')->exists($this->audioPath)) {
                Storage::disk('private')->delete($this->audioPath);
            }
        } catch (\Throwable $e) {
            Log::error('ProcessSpeakingAudio — audio deletion failed', [
                'audio_path' => $this->audioPath,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
