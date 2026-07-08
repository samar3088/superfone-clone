<?php

use App\Http\Controllers\AddonController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\OrgController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // --- Home (call / customer / staff insights + subscription overview) ---
    Route::get('/home', [HomeController::class, 'index']);

    // --- Teams (orgs) ---
    Route::get('/orgs', [OrgController::class, 'index']);
    Route::post('/orgs/{org}/renew', [OrgController::class, 'renew']);

    // --- Add-ons (Teams → Addon Purchase, paid from wallet) ---
    Route::get('/addons', [AddonController::class, 'index']);
    Route::get('/addon-purchases', [AddonController::class, 'purchases']);
    Route::post('/addons/purchase', [AddonController::class, 'purchase']);

    // --- Team Members ---
    Route::apiResource('team-members', TeamMemberController::class)
        ->parameters(['team-members' => 'teamMember'])
        ->except('show');

    // --- Settings (tags, call, CRM, custom fields, priority) ---
    Route::prefix('settings')->group(function () {
        Route::get('/tags', [SettingController::class, 'tags']);
        Route::post('/tags', [SettingController::class, 'storeTag']);
        Route::patch('/tags/{tag}', [SettingController::class, 'updateTag']);
        Route::delete('/tags/{tag}', [SettingController::class, 'destroyTag']);

        Route::get('/lead-stages', [SettingController::class, 'leadStages']);
        Route::post('/lead-stages', [SettingController::class, 'storeLeadStages']);
        Route::patch('/lead-stages/{leadStage}/toggle', [SettingController::class, 'toggleLeadStage']);
        Route::delete('/lead-stages/{leadStage}', [SettingController::class, 'destroyLeadStage']);
        Route::get('/lead-stage-templates', [SettingController::class, 'stageTemplates']);

        Route::get('/lead-groups', [SettingController::class, 'leadGroups']);
        Route::post('/lead-groups', [SettingController::class, 'storeLeadGroups']);
        Route::patch('/lead-groups/{leadGroup}/toggle', [SettingController::class, 'toggleLeadGroup']);

        Route::get('/custom-fields', [SettingController::class, 'customFields']);
        Route::post('/custom-fields', [SettingController::class, 'storeCustomField']);
        Route::delete('/custom-fields/{customField}', [SettingController::class, 'destroyCustomField']);

        Route::get('/call', [SettingController::class, 'callSettings']);
        Route::patch('/call', [SettingController::class, 'updateCallSettings']);
        Route::post('/ring-order', [SettingController::class, 'saveRingOrder']);

        Route::get('/priority', [SettingController::class, 'priorityFields']);
        Route::put('/priority', [SettingController::class, 'savePriorityFields']);
    });

    // --- Integrations (lead sources) ---
    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::post('/integrations', [IntegrationController::class, 'store']);
    Route::patch('/integrations/{integration}/toggle', [IntegrationController::class, 'toggle']);
    Route::delete('/integrations/{integration}', [IntegrationController::class, 'destroy']);
    Route::get('/integrations/facebook/pages', [IntegrationController::class, 'facebookPages']);
    Route::get('/integrations/facebook/pages/{pageId}/forms', [IntegrationController::class, 'facebookForms']);
});
