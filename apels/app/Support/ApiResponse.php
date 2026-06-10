<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Standardized API response helper for the APELS platform.
 *
 * Implements the API response contract defined in Requirement 17:
 *  - 17.1 Success structure: { status: 'success', message, data }
 *  - 17.2 Error structure:   { status: 'error',   message, errors, code }
 *  - 17.3 For 4xx/5xx responses, the `code` field equals the HTTP status code.
 *  - 17.4 All responses are JSON with `Content-Type: application/json`.
 */
class ApiResponse
{
    /**
     * Build a standardized success JSON response.
     *
     * @param  string                $message HTTP-friendly message describing the result.
     * @param  array<string, mixed>  $data    Payload returned to the caller. Cast to object so an
     *                                        empty payload is encoded as `{}` (object) rather than
     *                                        `[]` (array), matching the shape documented in design §16.
     * @param  int                   $status  HTTP status code. Defaults to 200 OK.
     */
    public static function success(string $message, array $data = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => (object) $data,
        ], $status, ['Content-Type' => 'application/json']);
    }

    /**
     * Build a standardized error JSON response.
     *
     * The HTTP status code is taken from `$code` so the body field `code` always
     * mirrors the response status (Req 17.3).
     *
     * @param  string                $message Human-readable error message.
     * @param  int                   $code    HTTP status code (used for both header and body field).
     * @param  array<string, mixed>  $errors  Per-field validation errors or detail map. Cast to
     *                                        object for consistent JSON shape when empty.
     */
    public static function error(string $message, int $code, array $errors = []): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => (object) $errors,
            'code'    => $code,
        ], $code, ['Content-Type' => 'application/json']);
    }
}
