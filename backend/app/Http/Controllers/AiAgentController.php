<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use Illuminate\Http\Request;

class AiAgentController extends Controller
{
    public function index(Request $request)
    {
        $query = AiAgent::with('customer:id,business_name');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $agents = $query->latest()->get();

        return response()->json([
            'agents' => $agents,
            'summary' => [
                'total' => $agents->count(),
                'active' => $agents->where('status', 'active')->count(),
                'handled' => (int) $agents->sum('handled'),
                'avg_resolution' => $agents->count()
                    ? round($agents->avg('resolution_rate'), 1)
                    : 0,
            ],
        ]);
    }

    public function store(Request $request)
    {
        return response()->json(
            AiAgent::create($this->validateData($request))->load('customer:id,business_name'),
            201
        );
    }

    public function update(Request $request, AiAgent $aiAgent)
    {
        $aiAgent->update($this->validateData($request));

        return $aiAgent->load('customer:id,business_name');
    }

    /**
     * Flip an agent between active and paused.
     */
    public function toggle(AiAgent $aiAgent)
    {
        $aiAgent->update([
            'status' => $aiAgent->status === 'active' ? 'paused' : 'active',
        ]);

        return $aiAgent;
    }

    public function destroy(AiAgent $aiAgent)
    {
        $aiAgent->delete();

        return response()->json(['message' => 'Agent deleted']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:sales,receptionist,whatsapp,payment,manager'],
            'channel' => ['required', 'in:call,whatsapp,both'],
            'status' => ['required', 'in:active,paused,draft'],
            'persona' => ['nullable', 'string', 'max:1024'],
        ]);
    }
}
