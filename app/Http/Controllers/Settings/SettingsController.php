<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\FieldPriority;
use App\Models\Integration;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Models\Tag;
use App\Models\User;
use App\Services\Support\SettingsService;
use App\Support\LeadProviders;
use App\Support\Permissions;
use App\Support\Roles;
use App\Support\Settings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner-only settings. Reads for all three tabs are assembled here; writes
 * live in the dedicated resource controllers.
 */
class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function index(): Response
    {
        abort_unless(request()->user()?->can(Permissions::SETTINGS_VIEW), 403);

        return Inertia::render('settings/index', [
            'tags' => Tag::orderBy('id')->get(),
            'leadStages' => LeadStage::orderBy('sequence')->withCount('leads')->get(),
            'leadGroups' => LeadGroup::orderBy('id')->get(),
            'customFields' => CustomField::orderBy('sequence')->orderBy('id')->get(),
            'fieldPriorities' => FieldPriority::orderBy('sequence')->get()->groupBy('section'),
            'integrations' => Integration::with(['members:id,name', 'creator:id,name'])
                ->latest()->get()
                ->map(fn (Integration $i) => [
                    ...$i->toArray(),
                    'is_configured' => $i->isConfigured(),
                ]),
            'providers' => LeadProviders::all(),
            'providerTabs' => LeadProviders::tabs(),
            'members' => User::role(Roles::MEMBER)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'stageTypes' => ['INITIAL', 'NONE', 'FINAL_POSITIVE', 'FINAL_NEGATIVE'],
            'fieldTypes' => ['text', 'number', 'date', 'dropdown', 'checkbox'],
            'appSettings' => [
                'new_lead_email' => $this->settings->boolean(Settings::NEW_LEAD_EMAIL),
                // Only whether a token exists — never the token itself.
                'facebook_token_set' => $this->settings->has(Settings::FACEBOOK_TOKEN),
            ],
        ]);
    }
}
