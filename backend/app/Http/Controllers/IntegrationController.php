<?php

namespace App\Http\Controllers;

use App\LeadSources\FacebookLeadSource;
use App\Models\Customer;
use App\Models\Integration;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        $orgIds = Customer::where('user_id', $request->user()->id)->pluck('id');

        $query = Integration::with('customer:id,business_name')
            ->whereIn('customer_id', $orgIds);

        if ($provider = $request->query('provider')) {
            $query->where('provider', $provider);
        }

        return $query->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'provider' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'page_name' => ['nullable', 'string', 'max:150'],
            'form_name' => ['nullable', 'string', 'max:150'],
        ]);

        $org = Customer::findOrFail($data['customer_id']);
        abort_unless($org->user_id === $request->user()->id, 403);

        // Facebook connections carry the linked FB account name.
        if ($data['provider'] === 'facebook') {
            $data['connected_account'] = (new FacebookLeadSource())->account()['name'];
        }

        $data['status'] = 'active';
        $data['created_by'] = $request->user()->name;

        return response()->json(
            Integration::create($data)->load('customer:id,business_name'),
            201
        );
    }

    public function toggle(Request $request, Integration $integration)
    {
        abort_unless($integration->customer->user_id === $request->user()->id, 403);
        $integration->update([
            'status' => $integration->status === 'active' ? 'paused' : 'active',
        ]);

        return $integration;
    }

    public function destroy(Request $request, Integration $integration)
    {
        abort_unless($integration->customer->user_id === $request->user()->id, 403);
        $integration->delete();

        return response()->json(['message' => 'Integration removed']);
    }

    /** Connected FB account + its pages (simulated until Meta app creds exist). */
    public function facebookPages()
    {
        $fb = new FacebookLeadSource();

        return response()->json([
            'account' => $fb->account(),
            'pages' => $fb->pages(),
        ]);
    }

    /** Lead forms of a page — fetched from the connected account. */
    public function facebookForms(string $pageId)
    {
        return response()->json((new FacebookLeadSource())->forms($pageId));
    }
}
