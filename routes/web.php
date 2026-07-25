<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 | Guest — mobile + OTP login, with a password fallback.
 */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');

    // Throttled so the OTP endpoint can't be farmed for SMS cost or enumeration.
    Route::post('login/otp', [LoginController::class, 'requestOtp'])
        ->middleware('throttle:6,1')
        ->name('login.otp.request');

    Route::post('login/verify', [LoginController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')
        ->name('login.otp.verify');

    Route::post('login/password', [LoginController::class, 'passwordLogin'])
        ->middleware('throttle:10,1')
        ->name('login.password');
});

Route::post('logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
 | Authenticated application.
 */
Route::middleware(['auth'])->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');
});

require __DIR__.'/settings.php';
