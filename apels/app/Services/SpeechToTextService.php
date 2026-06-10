<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * SpeechToTextService — OpenAI Whisper wrapper for speaking score.
 *
 * Sends audio file to Whisper API, counts words in transcript,
 * and computes a speaking score (0..100).
 *
 * Scoring formula (Req 6.2, 6.3):
 *   - n >= 20 words → score = 100
 *   - 5 <= n < 20   → score = round(n / 20 * 100)
 *   - 0 < n < 5     → score = max(round(n / 20 * 100), 10)
 *   - n = 0         → score = 0
 *
 * Error handling (Req 6.7, 23.4, 27.1):
 *   - File not found → log error, return 0
 *   - API exception/timeout → log error, return 0
 *
 * Requirements: 6.1, 6.2, 6.3, 6.7, 23.4, 25.3, 27.1, 28.3
 */
class SpeechToTextService
{
    /**
     * Analyze audio file and return speaking score (0..100).
     *
     * @param  string  $audioPath  Relative path on 'private' disk.
     * @return int                 Speaking score 0..100.
     */
    public function analyze(string $audioPath): int
    {
        // Check file exists on private disk (Req 23.4)
        if (!Storage::disk('private')->exists($audioPath)) {
            Log::error('SpeechToTextService::analyze — file not found', [
                'audio_path' => $audioPath,
            ]);
            return 0;
        }

        try {
            $absolutePath = Storage::disk('private')->path($audioPath);

            $response = OpenAI::audio()->transcribe([
                'model'    => 'whisper-1',
                'file'     => fopen($absolutePath, 'r'),
                'language' => 'en',
            ]);

            $transcript = $response->text ?? '';

            return $this->scoreFromTranscript($transcript);
        } catch (\Throwable $e) {
            Log::error('SpeechToTextService::analyze — API failure', [
                'audio_path' => $audioPath,
                'error'      => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Compute speaking score from transcript word count.
     *
     * Exposed as public for PBT (Property 8).
     *
     * @param  string  $transcript
     * @return int     Score 0..100
     */
    public function scoreFromTranscript(string $transcript): int
    {
        $n = str_word_count(trim($transcript));

        return $this->scoreFromWordCount($n);
    }

    /**
     * Pure scoring logic from word count (Req 6.2, 6.3).
     *
     * @param  int  $n  Word count >= 0
     * @return int      Score 0..100
     */
    public function scoreFromWordCount(int $n): int
    {
        if ($n <= 0) {
            return 0;
        }

        $raw = (int) round($n / 20 * 100);

        if ($n >= 20) {
            return 100;
        }

        if ($n < 5) {
            return max($raw, 10); // Req 6.3: minimum 10 for short transcripts
        }

        return min($raw, 100); // Req 6.2
    }
}
