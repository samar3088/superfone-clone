<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Crm\CustomerService;
use App\Services\Support\DataTableService;
use App\Services\Support\ExportService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customers,
        private ExportService $exports,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('customers/index', [
            'customers' => $this->table()->paginate($request),
            'filters' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }

    /** One customer with every lead they have ever raised. */
    public function show(Customer $customer): Response
    {
        return Inertia::render('customers/show', [
            'customer' => $customer->loadCount(['leads', 'calls']),
            'leads' => $this->customers->leadHistory($customer),
            'duplicates' => $this->customers->findPotentialDuplicates($customer),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', Rule::unique('customers', 'mobile')->ignore($customer->id)->whereNull('deleted_at')],
            'email' => ['nullable', 'email:rfc', 'max:150', Rule::unique('customers', 'email')->ignore($customer->id)->whereNull('deleted_at')],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'mobile.unique' => 'Another customer already uses this mobile number.',
            'email.unique' => 'Another customer already uses this email.',
        ]));

        return back()->with('success', 'Customer updated.');
    }

    /** Fold duplicate records into this one. */
    public function merge(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'duplicate_ids' => ['required', 'array', 'min:1'],
            'duplicate_ids.*' => ['integer', 'exists:customers,id'],
        ]);

        $this->customers->merge($customer, $data['duplicate_ids']);

        return back()->with('success', 'Customers merged. All their leads now sit under this record.');
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->exports->streamCsv(
            $this->table()->query($request)->withCount(['leads', 'calls']),
            ['Name', 'Mobile', 'Email', 'City', 'Leads', 'Calls', 'Last activity', 'Added on'],
            fn (Customer $c) => [
                $c->name, $c->mobile, $c->email ?? '', $c->city ?? '',
                $c->leads_count, $c->calls_count,
                $c->last_activity_at?->toDateTimeString() ?? '',
                $c->created_at->toDateTimeString(),
            ],
            'customers-'.now()->format('Y-m-d-Hi').'.csv',
        );
    }

    private function table(): DataTableService
    {
        return DataTableService::for(Customer::query()->active()->withCount('leads'))
            ->select(['id', 'name', 'mobile', 'email', 'city', 'last_activity_at', 'created_at'])
            ->searchable(['name', 'mobile', 'email'])
            ->sortable(['name', 'created_at', 'last_activity_at'])
            ->defaultSort('id', 'desc');
    }
}
