<?php

namespace App\Http\Controllers;

use App\Models\IvrFlow;
use Illuminate\Http\Request;

class IvrFlowController extends Controller
{
    public function index(Request $request)
    {
        $query = IvrFlow::with(['customer:id,business_name', 'options'])
            ->withCount('options');

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
        $flow = IvrFlow::create($data);
        $this->syncOptions($flow, $data['options'] ?? []);

        return response()->json($flow->load('customer:id,business_name', 'options'), 201);
    }

    public function update(Request $request, IvrFlow $ivrFlow)
    {
        $data = $this->validateData($request);
        $ivrFlow->update($data);
        $this->syncOptions($ivrFlow, $data['options'] ?? []);

        return $ivrFlow->load('customer:id,business_name', 'options');
    }

    public function toggle(IvrFlow $ivrFlow)
    {
        $ivrFlow->update([
            'status' => $ivrFlow->status === 'active' ? 'inactive' : 'active',
        ]);

        return $ivrFlow;
    }

    public function destroy(IvrFlow $ivrFlow)
    {
        $ivrFlow->delete();

        return response()->json(['message' => 'IVR flow deleted']);
    }

    /**
     * Replace a flow's keypad options with the submitted set.
     */
    private function syncOptions(IvrFlow $flow, array $options): void
    {
        $flow->options()->delete();
        foreach ($options as $opt) {
            $flow->options()->create([
                'key_press' => $opt['key_press'],
                'label' => $opt['label'],
                'action' => $opt['action'],
                'destination' => $opt['destination'] ?? null,
            ]);
        }
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:120'],
            'greeting' => ['nullable', 'string', 'max:1024'],
            'status' => ['required', 'in:active,inactive'],
            'options' => ['array'],
            'options.*.key_press' => ['required', 'string', 'max:2'],
            'options.*.label' => ['required', 'string', 'max:120'],
            'options.*.action' => ['required', 'in:forward,voicemail,submenu,hangup,repeat'],
            'options.*.destination' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
