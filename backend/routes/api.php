<?php

use App\Http\Controllers\AddonController;
use App\Http\Controllers\AiAgentController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallerTuneController;
use App\Http\Controllers\CallLogController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\IvrFlowController;
use App\Http\Controllers\LiveCallController;
use App\Http\Controllers\OrgController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MessageTemplateController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\VirtualNumberController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Telephony webhook — real providers (Exotel/Twilio/…) will POST here.
// The simulate button uses the same endpoint with driver "simulated".
Route::post('/telephony/webhook/{driver}', [LiveCallController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('plans', PlanController::class)->except('show');
    Route::apiResource('virtual-numbers', VirtualNumberController::class)
        ->parameters(['virtual-numbers' => 'virtual_number'])
        ->except('show');
    Route::get('/call-logs', [CallLogController::class, 'index']);

    // --- CRM ---
    Route::apiResource('contacts', ContactController::class)->except('show');

    Route::apiResource('leads', LeadController::class)->except('show');
    Route::patch('/leads/{lead}/move', [LeadController::class, 'move']);

    Route::apiResource('reminders', ReminderController::class)->except('show');
    Route::patch('/reminders/{reminder}/toggle', [ReminderController::class, 'toggle']);

    // --- Team ---
    Route::get('/team-members/leaderboard', [TeamMemberController::class, 'leaderboard']);
    Route::apiResource('team-members', TeamMemberController::class)
        ->parameters(['team-members' => 'teamMember'])
        ->except('show');

    // --- Billing & analytics ---
    Route::get('/payments/summary', [PaymentController::class, 'summary']);
    Route::patch('/payments/{payment}/paid', [PaymentController::class, 'markPaid']);
    Route::apiResource('payments', PaymentController::class)->except('show');

    Route::get('/analytics', [AnalyticsController::class, 'index']);

    // --- WhatsApp & messaging ---
    Route::apiResource('templates', MessageTemplateController::class)
        ->parameters(['templates' => 'messageTemplate'])
        ->except('show');

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/reply', [ConversationController::class, 'reply']);
    Route::patch('/conversations/{conversation}/close', [ConversationController::class, 'close']);

    Route::post('/campaigns/{campaign}/send', [CampaignController::class, 'send']);
    Route::apiResource('campaigns', CampaignController::class)->except('show');

    // --- AI Agents ---
    Route::patch('/ai-agents/{aiAgent}/toggle', [AiAgentController::class, 'toggle']);
    Route::apiResource('ai-agents', AiAgentController::class)
        ->parameters(['ai-agents' => 'aiAgent'])
        ->except('show');

    // --- Owner home / orgs / addons ---
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/orgs', [OrgController::class, 'index']);
    Route::post('/orgs/{org}/renew', [OrgController::class, 'renew']);
    Route::get('/addons', [AddonController::class, 'index']);
    Route::get('/addon-purchases', [AddonController::class, 'purchases']);
    Route::post('/addons/purchase', [AddonController::class, 'purchase']);

    // --- Settings (tags, CRM config, call settings, custom fields, priority) ---
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

    // --- Lead-source integrations ---
    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::post('/integrations', [IntegrationController::class, 'store']);
    Route::patch('/integrations/{integration}/toggle', [IntegrationController::class, 'toggle']);
    Route::delete('/integrations/{integration}', [IntegrationController::class, 'destroy']);
    Route::get('/integrations/facebook/pages', [IntegrationController::class, 'facebookPages']);
    Route::get('/integrations/facebook/pages/{pageId}/forms', [IntegrationController::class, 'facebookForms']);

    // --- Live calls (sticky-agent routing) ---
    Route::get('/live-calls', [LiveCallController::class, 'index']);
    Route::post('/live-calls/{liveCall}/answer', [LiveCallController::class, 'answer']);
    Route::post('/live-calls/{liveCall}/escalate', [LiveCallController::class, 'escalate']);
    Route::post('/live-calls/{liveCall}/hangup', [LiveCallController::class, 'hangup']);

    // --- Call features: IVR + caller tunes ---
    Route::patch('/ivr-flows/{ivrFlow}/toggle', [IvrFlowController::class, 'toggle']);
    Route::apiResource('ivr-flows', IvrFlowController::class)
        ->parameters(['ivr-flows' => 'ivrFlow'])
        ->except('show');

    Route::patch('/caller-tunes/{callerTune}/activate', [CallerTuneController::class, 'activate']);
    Route::apiResource('caller-tunes', CallerTuneController::class)
        ->parameters(['caller-tunes' => 'callerTune'])
        ->except('show');
});
