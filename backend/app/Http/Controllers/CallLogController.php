<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    public function index(Request $request)
    {
        $query = CallLog::with(['customer:id,business_name', 'agent:id,name']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('caller', 'like', "%{$search}%")
                    ->orWhere('virtual_number', 'like', "%{$search}%");
            });
        }

        if ($direction = $request->query('direction')) {
            $query->where('direction', $direction);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $query->latest('called_at')->paginate(15)->withQueryString();
    }
}
