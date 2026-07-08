<?php

namespace App\Http\Controllers;

use App\Models\CallerTune;
use Illuminate\Http\Request;

class CallerTuneController extends Controller
{
    public function index(Request $request)
    {
        $query = CallerTune::with('customer:id,business_name');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($genre = $request->query('genre')) {
            $query->where('genre', $genre);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $query->latest()->paginate(10)->withQueryString();
    }

    public function store(Request $request)
    {
        $tune = CallerTune::create($this->validateData($request));

        return response()->json($tune->load('customer:id,business_name'), 201);
    }

    public function update(Request $request, CallerTune $callerTune)
    {
        $callerTune->update($this->validateData($request));

        return $callerTune->load('customer:id,business_name');
    }

    /**
     * Activate this tune (and deactivate the business's other tunes,
     * since only one caller tune plays per number).
     */
    public function activate(CallerTune $callerTune)
    {
        CallerTune::where('customer_id', $callerTune->customer_id)
            ->where('id', '!=', $callerTune->id)
            ->update(['status' => 'inactive']);

        $callerTune->update(['status' => 'active']);

        return $callerTune;
    }

    public function destroy(CallerTune $callerTune)
    {
        $callerTune->delete();

        return response()->json(['message' => 'Caller tune deleted']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:120'],
            'genre' => ['required', 'in:default,corporate,festive,custom'],
            'duration_sec' => ['required', 'integer', 'min:5', 'max:120'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
