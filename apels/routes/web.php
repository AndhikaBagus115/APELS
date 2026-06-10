<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    $user = Auth::user();

    if ($user->hasRole('admin')) {
        return redirect('/admin');
    } elseif ($user->hasRole('dosen')) {
        return redirect()->route('reports');
    } else {
        return redirect()->route('dashboard');
    }
})->name('home');

/*
|--------------------------------------------------------------------------
| Mahasiswa Routes (Req 2.2)
|--------------------------------------------------------------------------
| Group: auth + role:mahasiswa
| Routes: /dashboard, /learning-path, /exercises, /diagnostic
*/
Route::middleware(['auth', 'role:mahasiswa'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Volt::route('learning-path', 'student.learning-path')->name('learning-path');
    Volt::route('learning-path/{module}', 'student.module-detail')->name('module-detail');
    Volt::route('exercises/{module}', 'student.exercise')->name('exercises');

    // Diagnostic Test — tambahan middleware daily.test.limit (Req 3)
    Route::middleware(['daily.test.limit'])->group(function () {
        Volt::route('diagnostic', 'student.diagnostic-test')->name('diagnostic');
    });
});

/*
|--------------------------------------------------------------------------
| Dosen Routes (Req 2.4)
|--------------------------------------------------------------------------
| Group: auth + role:dosen
*/
Route::middleware(['auth', 'role:dosen'])->group(function () {
    Volt::route('reports', 'lecturer.reports')->name('reports');
});

/*
|--------------------------------------------------------------------------
| API Routes (Diagnostic Submit) — Req 4, 5, 17
|--------------------------------------------------------------------------
| Defined in routes/api.php (Task 11.2)
*/

/*
|--------------------------------------------------------------------------
| Settings (existing from Livewire Starter Kit)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
