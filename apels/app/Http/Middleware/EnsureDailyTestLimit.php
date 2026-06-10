<?php

namespace App\Http\Middleware;

use App\Models\DiagnosticResult;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Daily_Test_Limit_Middleware — blokir akses Diagnostic Test jika user
 * sudah mengerjakan tes pada hari kalender yang sama (Req 3).
 *
 * "Hari kalender" dihitung berdasarkan timezone aplikasi (`config/app.php`),
 * sehingga `today()` di Carbon menggunakan timezone yang sama (Req 3.3).
 *
 * Alias: `daily.test.limit` (didaftarkan di `bootstrap/app.php`). Middleware
 * ini dipasang pada route Diagnostic Test (lihat Task 11.2).
 */
class EnsureDailyTestLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->id;

        if ($userId === null) {
            // Auth middleware handles unauthenticated users; this is a defensive guard
            // so the middleware tetap aman jika dipasang tanpa `auth` di depannya.
            return ApiResponse::error('Tidak terautentikasi.', 401);
        }

        $alreadyTestedToday = DiagnosticResult::query()
            ->where('user_id', $userId)
            ->whereDate('created_at', today())
            ->exists();

        if ($alreadyTestedToday) {
            return ApiResponse::error(
                'Anda sudah mengerjakan tes hari ini. Coba lagi besok.',
                429
            );
        }

        return $next($request);
    }
}
