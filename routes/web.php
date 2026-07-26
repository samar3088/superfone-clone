<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\Settings\AppSettingsController;
use App\Http\Controllers\Settings\CrmSettingsController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\TagController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Team\MemberController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

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

    // Dashboard + its three date-ranged exports
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/export/calls', [DashboardController::class, 'exportCalls'])->name('dashboard.export.calls');
    Route::get('dashboard/export/customers', [DashboardController::class, 'exportCustomers'])->name('dashboard.export.customers');
    Route::get('dashboard/export/staff', [DashboardController::class, 'exportStaff'])->name('dashboard.export.staff');

    // Own account
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Leads
    // "todos" rather than "tasks": the UI calls them to-dos, and the settings
    // that raise them are labelled "create a to-do".
    Route::get('todos', [TaskController::class, 'index'])->name('todos.index');
    Route::patch('todos/{task}/complete', [TaskController::class, 'complete'])->name('todos.complete');
    Route::patch('todos/{task}/reopen', [TaskController::class, 'reopen'])->name('todos.reopen');

    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('leads/export', [LeadController::class, 'export'])->name('leads.export');
    Route::post('leads/mark-read', [LeadController::class, 'markAllRead'])->name('leads.read');
    Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status');

    // Customers — one person, many leads
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::get('customers/import/sample', [CustomerController::class, 'importSample'])->name('customers.import.sample');
    Route::post('customers/import/check', [CustomerController::class, 'importCheck'])->name('customers.import.check');
    Route::post('customers/import', [CustomerController::class, 'import'])->name('customers.import');
    Route::get('customers/duplicates', [CustomerController::class, 'duplicates'])->name('customers.duplicates');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('customers/{customer}/merge', [CustomerController::class, 'merge'])->name('customers.merge');

    // Notes — always against the contact, optionally against one of their leads
    Route::get('customers/{customer}/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::post('customers/{customer}/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::patch('notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    // Team members
    /*
     | Mind the pair: `teams.*` is the organisation and its plan, `team.*` is
     | the staff roster. Different things, one letter apart.
     */
    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');

    Route::get('team/export', [MemberController::class, 'export'])->name('team.export');
    Route::get('team', [MemberController::class, 'index'])->name('team.index');
    Route::post('team', [MemberController::class, 'store'])->name('team.store');
    Route::patch('team/{member}', [MemberController::class, 'update'])->name('team.update');
    Route::delete('team/{member}', [MemberController::class, 'destroy'])->name('team.destroy');

    Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');

    /*
     | Settings — owner only. Business Management, CRM Settings, Integrations.
     */
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');

        // Business Management → Tags
        Route::post('tags', [TagController::class, 'store'])->name('tags.store');
        Route::patch('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        // CRM Settings → Lead Stage / Lead Group / Custom Fields / Field Priority
        Route::post('lead-stages', [CrmSettingsController::class, 'storeStage'])->name('stages.store');
        Route::patch('lead-stages/reorder', [CrmSettingsController::class, 'reorderStages'])->name('stages.reorder');
        Route::patch('lead-stages/{stage}', [CrmSettingsController::class, 'updateStage'])->name('stages.update');
        Route::delete('lead-stages/{stage}', [CrmSettingsController::class, 'destroyStage'])->name('stages.destroy');

        Route::post('lead-groups', [CrmSettingsController::class, 'storeGroup'])->name('groups.store');
        Route::patch('lead-groups/{group}', [CrmSettingsController::class, 'updateGroup'])->name('groups.update');

        Route::post('custom-fields', [CrmSettingsController::class, 'storeField'])->name('fields.store');
        Route::patch('custom-fields/{field}', [CrmSettingsController::class, 'updateField'])->name('fields.update');
        Route::delete('custom-fields/{field}', [CrmSettingsController::class, 'destroyField'])->name('fields.destroy');

        Route::put('field-priority', [CrmSettingsController::class, 'saveFieldPriority'])->name('priority.save');

        // Notifications + stored credentials
        Route::put('notifications', [AppSettingsController::class, 'updateNotifications'])->name('notifications.save');
        Route::put('facebook-token', [AppSettingsController::class, 'updateFacebookToken'])->name('fb.token.save');
        Route::delete('facebook-token', [AppSettingsController::class, 'clearFacebookToken'])->name('fb.token.clear');

        // Integrations → Facebook. Static segments sit above {integration}.
        Route::get('integrations/facebook/pages', [IntegrationController::class, 'facebookPages'])->name('fb.pages');
        Route::get('integrations/facebook/pages/{pageId}/forms', [IntegrationController::class, 'facebookForms'])->name('fb.forms');
        Route::post('integrations', [IntegrationController::class, 'store'])->name('integrations.store');
        Route::get('integrations/{integration}/preview', [IntegrationController::class, 'preview'])->name('integrations.preview');
        Route::get('integrations/{integration}', [IntegrationController::class, 'show'])->name('integrations.show');
        Route::patch('integrations/{integration}/settings', [IntegrationController::class, 'updateSettings'])->name('integrations.settings');
        Route::post('integrations/{integration}/sync', [IntegrationController::class, 'sync'])->name('integrations.sync');
        Route::patch('integrations/{integration}/toggle', [IntegrationController::class, 'toggle'])->name('integrations.toggle');
        Route::delete('integrations/{integration}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
    });
});
