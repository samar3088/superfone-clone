<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreIntegrationRequest;
use App\Http\Requests\Settings\UpdateIntegrationSettingsRequest;
use App\LeadSources\FacebookLeadSource;
use App\Models\Integration;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Models\Tag;
use App\Models\User;
use App\Services\Crm\FacebookSyncService;
use App\Support\LeadProviders;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(private FacebookLeadSource $facebook) {}

    private function guard(): void
    {
        abort_unless(request()->user()?->can(Permissions::INTEGRATION_MANAGE), 403);
    }

    /** Connected account plus its pages, for step 2 of the wizard. */
    public function facebookPages(): JsonResponse
    {
        $this->guard();

        return response()->json([
            'account' => $this->facebook->account(),
            'pages' => $this->facebook->pages(),
        ]);
    }

    public function facebookForms(string $pageId): JsonResponse
    {
        $this->guard();

        return response()->json($this->facebook->forms($pageId));
    }

    /**
     * Wizard completion: name + page + form. The routing rules are set
     * afterwards on the integration's own Configuration screen.
     */
    public function store(StoreIntegrationRequest $request): RedirectResponse
    {
        $page = $this->facebook->page($request->validated('external_page_id'));

        $integration = Integration::create([
            ...$request->validated(),
            'page_name' => $page['name'] ?? null,
            'page_picture' => $page['picture'] ?? null,
            'page_description' => $page['description'] ?? null,
            'connected_account' => $this->facebook->account()['name'],
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        $integration->logs()->create([
            'status' => 'success',
            'message' => 'Integration created and connected to '.$integration->form_name,
        ]);

        return redirect()
            ->route('settings.integrations.show', $integration)
            ->with('success', 'Integration created. Set how its leads should be handled below.');
    }

    /** Configuration + Logs for one integration. */
    public function show(Integration $integration): Response
    {
        $this->guard();

        $integration->load([
            'members:id,name',
            'tags:id,name,color,emoji',
            'leadStage:id,name,emoji',
            'leadGroup:id,name',
            'creator:id,name',
        ]);

        return Inertia::render('settings/integration', [
            'integration' => [
                ...$integration->toArray(),
                'is_configured' => $integration->isConfigured(),
                'leads_count' => $integration->leads()->count(),
            ],
            'logs' => $integration->logs()->limit(50)->get(),
            'options' => [
                'members' => User::role([Roles::OWNER, Roles::MEMBER])
                    ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'stages' => LeadStage::where('is_active', true)->orderBy('sequence')
                    ->get(['id', 'name', 'emoji']),
                'groups' => LeadGroup::where('is_active', true)->get(['id', 'name']),
                'tags' => Tag::where('is_hidden', false)->orderBy('name')
                    ->get(['id', 'name', 'color', 'emoji']),
                'sourceTypes' => LeadProviders::sourceTypes(),
                'todoTypes' => LeadProviders::todoTypes(),
            ],
        ]);
    }

    /** Save the Source / Assignment / New-lead rules. */
    public function updateSettings(UpdateIntegrationSettingsRequest $request, Integration $integration): RedirectResponse
    {
        $data = $request->validated();

        $integration->update(collect($data)->except(['member_ids', 'tag_ids'])->all());
        $integration->members()->sync($data['member_ids'] ?? []);
        $integration->tags()->sync($data['tag_ids'] ?? []);

        return back()->with('success', 'Integration settings saved.');
    }

    /** Pull new leads from the connected form on demand. */
    public function sync(Integration $integration, FacebookSyncService $sync): RedirectResponse
    {
        $this->guard();

        try {
            /*
             | Bounded on purpose. A busy form can hold thousands of leads and
             | this runs inside the request — the scheduled sync picks up
             | whatever is left rather than the browser waiting on it.
             */
            $result = $sync->sync($integration, ['max_pages' => 5]);
        } catch (\Throwable $e) {
            // Already recorded on the Logs tab by the service.
            return back()->with('error', 'Sync failed: '.$e->getMessage());
        }

        if (! $result['complete']) {
            return back()->with(
                'success',
                "Imported {$result['imported']} lead(s). More are still queued — they will arrive over the next few minutes."
            );
        }

        return back()->with(
            'success',
            $result['imported'] > 0
                ? "Imported {$result['imported']} new lead(s)."
                : 'Already up to date — no new leads.'
        );
    }

    public function toggle(Integration $integration): RedirectResponse
    {
        $this->guard();

        $integration->update([
            'status' => $integration->status === 'active' ? 'paused' : 'active',
        ]);

        return back()->with('success', 'Integration '.$integration->status.'.');
    }

    public function destroy(Integration $integration): RedirectResponse
    {
        $this->guard();

        $name = $integration->name;
        $integration->delete();

        return redirect()
            ->route('settings.index')
            ->with('success', "“{$name}” removed.");
    }
}
