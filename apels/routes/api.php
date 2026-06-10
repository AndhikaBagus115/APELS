<?php

use App\Http\Controllers\Api\DiagnosticController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — APELS Platform
|--------------------------------------------------------------------------
|
| Semua route di file ini otomatis mendapat prefix `/api`.
| Auth menggunakan session-based (web guard) karena Livewire SPA.
|
*/

Route::middleware(['auth', 'role:mahasiswa', 'daily.test.limit'])->group(function () {
    Route::post('diagnostic/submit', [DiagnosticController::class, 'submit'])
        ->name('api.diagnostic.submit');
});
