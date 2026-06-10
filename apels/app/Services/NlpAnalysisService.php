<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * NlpAnalysisService — OpenAI GPT-4o Mini wrapper for grammar & vocabulary scoring.
 *
 * Sends combined text answers to GPT-4o Mini, requests JSON response with
 * grammar_score and vocab_score (0..100), caches result for 24h.
 *
 * Cache key (Req 7.5): nlp_result_{md5(implode('|', textAnswers))}
 * TTL: 86400 seconds (24 hours)
 *
 * Error handling (Req 7.4, 27.2):
 *   - Exception/timeout/parse error → log + return ['grammar_score' => 0, 'vocab_score' => 0]
 *   - Empty/whitespace-only input → return zeros without API call (Req 7 refined)
 *
 * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 25.3, 27.2, 28.3
 */
class NlpAnalysisService
{
    /**
     * Analyze text answers and return grammar + vocabulary scores.
     *
     * @param  array<int, string>  $textAnswers  Array of text answer strings.
     * @return array{grammar_score: int, vocab_score: int}
     */
    public function analyze(array $textAnswers): array
    {
        $fallback = ['grammar_score' => 0, 'vocab_score' => 0];

        // Filter empty/whitespace-only elements
        $filtered = array_filter($textAnswers, fn ($t) => is_string($t) && trim($t) !== '');

        if (empty($filtered)) {
            return $fallback;
        }

        // Cache key (Req 7.5)
        $cacheKey = 'nlp_result_' . md5(implode('|', $textAnswers));

        // Cache::remember — cache hit skips API call (Req 7.6)
        return Cache::remember($cacheKey, 86400, function () use ($textAnswers, $fallback) {
            return $this->callGpt4oMini($textAnswers, $fallback);
        });
    }

    /**
     * Call GPT-4o Mini Chat Completions API.
     *
     * @param  array<int, string>  $textAnswers
     * @param  array{grammar_score: int, vocab_score: int}  $fallback
     * @return array{grammar_score: int, vocab_score: int}
     */
    private function callGpt4oMini(array $textAnswers, array $fallback): array
    {
        try {
            $combinedText = implode(' ', $textAnswers); // Req 7.1

            $response = OpenAI::chat()->create([
                'model'           => 'gpt-4o-mini',
                'response_format' => ['type' => 'json_object'], // Req 7.2
                'max_tokens'      => 100,
                'messages'        => [
                    [
                        'role'    => 'system',
                        'content' => 'You are an English language evaluator. Analyze the given text and return ONLY a JSON object with these exact keys: grammar_score (integer 0-100), vocab_score (integer 0-100). No explanation, no markdown, just the JSON object.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $combinedText,
                    ],
                ],
            ]);

            $content = $response->choices[0]->message->content ?? '';
            $parsed = json_decode($content, true);

            if (!is_array($parsed)) {
                Log::error('NlpAnalysisService — JSON parse failed', [
                    'raw_content' => $content,
                ]);
                return $fallback;
            }

            // Clamp to 0..100 (Req 7.3)
            $grammar = $this->clamp((int) ($parsed['grammar_score'] ?? 0));
            $vocab   = $this->clamp((int) ($parsed['vocab_score'] ?? 0));

            return ['grammar_score' => $grammar, 'vocab_score' => $vocab];
        } catch (\Throwable $e) {
            Log::error('NlpAnalysisService::callGpt4oMini — API failure', [
                'error' => $e->getMessage(),
            ]);
            return $fallback;
        }
    }

    /**
     * Clamp integer to 0..100 range.
     */
    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}
