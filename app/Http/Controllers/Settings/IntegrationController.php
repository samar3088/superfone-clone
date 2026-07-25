<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\LeadSources\FacebookLeadSource;
use App\Models\Integration;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    public function __construct(private FacebookLeadSource $facebook) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can(Permissions::INTEGRATION_MANAGE), 403);
    }

    /** Connected Facebook account and its pages. */
    public function facebookPages(Request $request): JsonResponse
    {
        $this->guard($request);

        return response()->json([
            'account' => $this->facebook->account(),
            'pages' => $this->facebook->pages(),
        ]);
    }

    /** Lead forms belonging to a page. */
    public function facebookForms(Request $request, string $pageId): JsonResponse
    {
        $this->guard($request);

        return response()->json($this->facebook->forms($pageId));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', Rule::in(['facebook'])],
            'external_page_id' => ['required', 'string', 'max:64'],
            'page_name' => ['required', 'string', 'max:150'],
            'external_form_id' => ['required', 'string', 'max:64'],
            'form_name' => ['required', 'string', 'max:150'],
            // Leads from this campaign are shared round-robin between these members.
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'member_ids.required' => 'Map at least one team member to receive these leads.',
        ]);

        $integration = Integration::create([
            ...collect($data)->except('member_ids')->all(),
            'connected_account' => $this->facebook->account()['name'],
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        $integration->members()->sync($data['member_ids']);

        return back()->with('success', "“{$integration->name}” connected. New leads will be shared between the mapped members.");
    }

    public function update(Request $request, Integration $integration): RedirectResponse
    {
        $this->guard($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'member_ids.required' => 'Map at least one team member to receive these leads.',
        ]);

        $integration->update(['name' => $data['name']]);
        $integration->members()->sync($data['member_ids']);

        return back()->with('success', 'Integration updated.');
    }

    public function toggle(Request $request, Integration $integration): RedirectResponse
    {
        $this->guard($request);

        $integration->update([
            'status' => $integration->status === 'active' ? 'paused' : 'active',
        ]);

        return back()->with('success', 'Integration '.$integration->status.'.');
    }

    public function destroy(Request $request, Integration $integration): RedirectResponse
    {
        $this->guard($request);

        $integration->delete();

        return back()->with('success', 'Integration removed.');
    }
}
