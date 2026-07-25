<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\Team\MemberController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 | Guest — mobile + OTP sign-in, password fallback, OTP-based password reset.
 */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');

    // Throttled so the OTP endpoint can't be farmed for SMS cost or enumeration.
    Route::post('login/otp', [LoginController::class, 'requestOtp'])
        ->middleware('throttle:6,1')->name('login.otp.request');
    Route::post('login/verify', [LoginController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')->name('login.otp.verify');
    Route::post('login/password', [LoginController::class, 'passwordLogin'])
        ->middleware('throttle:10,1')->name('login.password');

    Route::get('forgot-password', [PasswordResetController::class, 'show'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendCode'])
        ->middleware('throttle:6,1')->name('password.code');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,1')->name('password.reset');
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
 | Authenticated application.
 */
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');

    // Own account
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Leads (populated by the Facebook integration in a later phase)
    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::post('leads/mark-read', [LeadController::class, 'markAllRead'])->name('leads.read');

    // Team members — export sits above the resource so it isn't caught by {member}
    Route::get('team/export', [MemberController::class, 'export'])->name('team.export');
    Route::get('team', [MemberController::class, 'index'])->name('team.index');
    Route::post('team', [MemberController::class, 'store'])->name('team.store');
    Route::patch('team/{member}', [MemberController::class, 'update'])->name('team.update');
    Route::delete('team/{member}', [MemberController::class, 'destroy'])->name('team.destroy');

    Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
});
