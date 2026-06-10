<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitDiagnosticRequest;
use App\Services\DiagnosticService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Storage;

/**
 * DiagnosticController — API endpoint for Diagnostic Test submission.
 *
 * POST /api/diagnostic/submit
 * Middleware: auth:sanctum, role:mahasiswa, daily.test.limit
 *
 * Requirements: 4.5, 5.5, 17.1, 17.4, 23.1
 */
class DiagnosticController extends Controller
{
    public function __construct(
        private DiagnosticService $diagnosticService,
    ) {}

    /**
     * Handle Diagnostic Test submission.
     */
    public function submit(SubmitDiagnosticRequest $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        // Store audio to private disk (Req 5.5, 23.1)
        $audioPath = null;
        if ($request->hasFile('audio')) {
            $audioPath = $request->file('audio')->store('diagnostic-audio', 'private');
        }

        $result = $this->diagnosticService->process($user->id, [
            'text_answers' => $request->validated('text_answers'),
            'audio_path'   => $audioPath,
        ]);

        return ApiResponse::success($result['message'], $result);
    }
}
