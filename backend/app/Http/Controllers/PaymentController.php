<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['customer:id,business_name', 'plan:id,name']);

        if ($search = $request->query('search')) {
            $query->where('invoice_no', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($q) => $q->where('business_name', 'like', "%{$search}%"));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $query->latest()->paginate(12)->withQueryString();
    }

    /**
     * Billing summary cards.
     */
    public function summary()
    {
        return response()->json([
            'collected' => (float) Payment::where('status', 'paid')->sum('amount'),
            'outstanding' => (float) Payment::where('status', 'pending')->sum('amount'),
            'this_month' => (float) Payment::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
            'failed' => Payment::where('status', 'failed')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['invoice_no'] = $this->nextInvoiceNo();
        $data['paid_at'] = $data['status'] === 'paid' ? now() : null;

        $payment = Payment::create($data);

        return response()->json($payment->load('customer:id,business_name', 'plan:id,name'), 201);
    }

    public function update(Request $request, Payment $payment)
    {
        $data = $this->validateData($request);
        // Stamp paid_at when transitioning into the paid state.
        if ($data['status'] === 'paid' && $payment->status !== 'paid') {
            $data['paid_at'] = now();
        } elseif ($data['status'] !== 'paid') {
            $data['paid_at'] = null;
        }
        $payment->update($data);

        return $payment->load('customer:id,business_name', 'plan:id,name');
    }

    /**
     * Quick "mark as paid" from the list.
     */
    public function markPaid(Payment $payment)
    {
        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        return $payment;
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();

        return response()->json(['message' => 'Payment deleted']);
    }

    private function nextInvoiceNo(): string
    {
        $year = now()->format('Y');
        $count = Payment::whereYear('created_at', $year)->count() + 1;

        return sprintf('INV-%s-%04d', $year, $count);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'in:card,upi,netbanking,wallet,cash'],
            'status' => ['required', 'in:paid,pending,failed,refunded'],
        ]);
    }
}
