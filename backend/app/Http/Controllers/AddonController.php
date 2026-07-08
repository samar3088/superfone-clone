<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\AddonPurchase;
use App\Models\Customer;
use Illuminate\Http\Request;

class AddonController extends Controller
{
    /** Add-on catalog for the purchase modal. */
    public function index()
    {
        return Addon::orderBy('id')->get();
    }

    /** The owner's past add-on purchases (wallet page). */
    public function purchases(Request $request)
    {
        $orgIds = Customer::where('user_id', $request->user()->id)->pluck('id');

        return AddonPurchase::with(['addon:id,name,icon', 'customer:id,business_name'])
            ->whereIn('customer_id', $orgIds)
            ->latest()
            ->get();
    }

    /**
     * Buy add-ons for an org, paid from the owner's wallet.
     * Body: { customer_id, items: [{ addon_id, quantity }] }
     */
    public function purchase(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.addon_id' => ['required', 'exists:addons,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $org = Customer::findOrFail($data['customer_id']);
        abort_unless($org->user_id === $request->user()->id, 403);

        $addons = Addon::whereIn('id', collect($data['items'])->pluck('addon_id'))->get()->keyBy('id');
        $total = collect($data['items'])->sum(
            fn ($item) => (float) $addons[$item['addon_id']]->price * $item['quantity']
        );

        $user = $request->user();
        if ((float) $user->wallet_balance < $total) {
            return response()->json([
                'message' => 'Insufficient wallet balance. Total ₹' . number_format($total, 2)
                    . ', available ₹' . $user->wallet_balance,
            ], 422);
        }

        $user->decrement('wallet_balance', $total);

        foreach ($data['items'] as $item) {
            $addon = $addons[$item['addon_id']];
            AddonPurchase::create([
                'customer_id' => $org->id,
                'addon_id' => $addon->id,
                'quantity' => $item['quantity'],
                'amount' => (float) $addon->price * $item['quantity'],
            ]);

            // Extra team member add-on raises the org's staff limit.
            if (str_contains(strtolower($addon->name), 'team member')) {
                $org->increment('staff_limit', $item['quantity']);
            }
            // Lead storage raises the leads limit by 10K per pack.
            if (str_contains(strtolower($addon->name), 'lead storage')) {
                $org->increment('leads_limit', 10000 * $item['quantity']);
            }
        }

        return response()->json([
            'message' => 'Add-ons purchased for ₹' . number_format($total, 2),
            'wallet_balance' => (float) $user->fresh()->wallet_balance,
        ]);
    }
}
