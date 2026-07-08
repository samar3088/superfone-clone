<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = Campaign::with(['customer:id,business_name', 'template:id,name']);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $query->latest()->paginate(10)->withQueryString();
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['recipients'] = $this->audienceSize($data['customer_id'], $data['segment']);

        $campaign = Campaign::create($data);

        return response()->json($campaign->load('customer:id,business_name', 'template:id,name'), 201);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $data = $this->validateData($request);
        $data['recipients'] = $this->audienceSize($data['customer_id'], $data['segment']);
        $campaign->update($data);

        return $campaign->load('customer:id,business_name', 'template:id,name');
    }

    /**
     * Simulate sending a broadcast: compute delivery/read stats.
     */
    public function send(Campaign $campaign)
    {
        $recipients = $this->audienceSize($campaign->customer_id, $campaign->segment);
        $delivered = (int) round($recipients * 0.94);
        $read = (int) round($delivered * 0.62);

        $campaign->update([
            'status' => 'completed',
            'recipients' => $recipients,
            'sent' => $recipients,
            'delivered' => $delivered,
            'read' => $read,
            'scheduled_at' => $campaign->scheduled_at ?? now(),
        ]);

        return $campaign->load('customer:id,business_name', 'template:id,name');
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted']);
    }

    /**
     * Number of contacts a segment resolves to for a business.
     */
    private function audienceSize(int $customerId, string $segment): int
    {
        $query = Contact::where('customer_id', $customerId);
        if ($segment !== 'all') {
            $query->where('tag', $segment);
        }

        return $query->count();
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'template_id' => ['nullable', 'exists:message_templates,id'],
            'name' => ['required', 'string', 'max:150'],
            'segment' => ['required', 'in:all,customer,prospect,vendor,other'],
            'status' => ['required', 'in:draft,scheduled,sending,completed'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
    }
}
