<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

/**
 * The owner's organizations — the "Teams" page.
 */
class OrgController extends Controller
{
    public function index(Request $request)
    {
        $orgs = Customer::where('user_id', $request->user()->id)
            ->with('plan:id,name,price,validity_days')
            ->withCount(['teamMembers as staff_count', 'leads as leads_count'])
            ->get()
            ->map(function ($org) {
                $number = $org->numbers()->where('status', 'assigned')->orderBy('id')->value('number');
                return [
                    'id' => $org->id,
                    'name' => $org->business_name,
                    'status' => $org->status,
                    'number' => $number,
                    'staff_count' => $org->staff_count,
                    'staff_limit' => $org->staff_limit,
                    'leads_count' => $org->leads_count,
                    'leads_limit' => $org->leads_limit,
                    'plan' => $org->plan,
                    'expires_at' => $org->expires_at,
                ];
            });

        return response()->json($orgs);
    }

    /**
     * Renew the org's plan from the owner's wallet.
     */
    public function renew(Request $request, Customer $org)
    {
        abort_unless($org->user_id === $request->user()->id, 403);
        abort_unless($org->plan, 422, 'This team has no plan to renew.');

        $user = $request->user();
        $price = (float) $org->plan->price;

        if ((float) $user->wallet_balance < $price) {
            return response()->json([
                'message' => 'Insufficient wallet balance. Available: ₹' . $user->wallet_balance,
            ], 422);
        }

        $user->decrement('wallet_balance', $price);

        $base = ($org->expires_at && $org->expires_at->isFuture()) ? $org->expires_at : now();
        $org->update([
            'expires_at' => $base->copy()->addDays($org->plan->validity_days),
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Plan renewed until ' . $org->expires_at->format('d M Y'),
            'wallet_balance' => (float) $user->fresh()->wallet_balance,
            'expires_at' => $org->expires_at,
        ]);
    }
}
