<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SubmitDiagnosticRequest — validation for Diagnostic Test submission.
 *
 * Rules (Req 5.1-5.3):
 *   - text_answers: required array, 3..20 elements
 *   - text_answers.*: required string, 5..500 chars
 *   - audio: required file, mimes wav/mp3/webm/ogg, max APELS_AUDIO_MAX_MB
 *
 * Failure returns HTTP 422 with API_Response error shape (Req 5.4).
 */
class SubmitDiagnosticRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('apels.audio_max_mb', 10) * 1024;

        return [
            'text_answers'   => ['required', 'array', 'min:3', 'max:20'],
            'text_answers.*' => ['required', 'string', 'min:5', 'max:500'],
            'audio'          => ['required', 'file', 'mimes:wav,mp3,webm,ogg', "max:{$maxKb}"],
        ];
    }

    /**
     * Custom error messages in Indonesian.
     */
    public function messages(): array
    {
        return [
            'text_answers.required' => 'Jawaban teks wajib diisi.',
            'text_answers.min'      => 'Minimal 3 jawaban teks diperlukan.',
            'text_answers.max'      => 'Maksimal 20 jawaban teks.',
            'text_answers.*.min'    => 'Setiap jawaban minimal 5 karakter.',
            'text_answers.*.max'    => 'Setiap jawaban maksimal 500 karakter.',
            'audio.required'        => 'File audio wajib diunggah.',
            'audio.mimes'           => 'Format audio harus wav, mp3, webm, atau ogg.',
            'audio.max'             => 'Ukuran audio maksimal ' . config('apels.audio_max_mb', 10) . ' MB.',
        ];
    }
}
