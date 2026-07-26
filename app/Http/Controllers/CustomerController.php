<?php

namespace App\Http\Controllers;

use App\Http\Requests\Crm\CustomerFilterRequest;
use App\Http\Requests\Crm\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Models\User;
use App\Services\Crm\CustomerService;
use App\Services\Crm\TaskService;
use App\Services\Support\DataTableService;
use App\Services\Support\ExportService;
use App\Support\FilterList;
use App\Support\LeadProviders;
use App\Support\Roles;
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

    /** Filter keys the screen owns; the export reads the same set. */
    private const FILTER_KEYS = ['search', 'member', 'leads', 'date_from', 'date_to'];

    public function index(CustomerFilterRequest $request): Response
    {
        return Inertia::render('customers/index', [
            'customers' => $this->table($request)->paginate($request),
            'filters' => $request->only([...self::FILTER_KEYS, 'sort', 'direction', 'per_page']),
            'members' => User::role([Roles::OWNER, Roles::MEMBER])->orderBy('name')->get(['id', 'name']),
            // Options for the Create Contact form.
            'options' => [
                'sourceTypes' => LeadProviders::sourceTypes(),
                'todoTypes' => LeadProviders::todoTypes(),
                'stages' => LeadStage::where('is_active', true)->orderBy('sequence')
                    ->get(['id', 'name', 'emoji']),
                'groups' => LeadGroup::where('is_active', true)->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * Create a contact by hand.
     *
     * Three things can come out of one submission: the contact itself, a lead
     * if a stage was chosen, and a to-do against that lead. Each only happens
     * if the form asked for it.
     */
    public function store(StoreCustomerRequest $request, TaskService $tasks): RedirectResponse
    {
        $data = $request->validated();

        ['customer' => $customer, 'existing' => $existing] = $this->customers->createManual($data);

        $lead = null;

        if (filled($data['lead_stage_id'] ?? null)) {
            $lead = Lead::create([
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'email' => $customer->email,
                // Nullable fields are absent from validated() when not sent,
                // so every read here has to tolerate a missing key.
                'source' => ($data['source'] ?? null) ?: 'manual',
                'source_type' => $data['source_type'] ?? null,
                'campaign' => $data['campaign'] ?? null,
                'deal_value' => $data['deal_value'] ?? null,
                'lead_stage_id' => $data['lead_stage_id'],
                'lead_group_id' => $data['lead_group_id'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                // Entered by hand, so whoever typed it has already seen it.
                'viewed_at' => now(),
            ]);
        }

        if ($lead && ($data['create_task'] ?? false)) {
            $tasks->createManual($lead, [
                'type' => $data['task_type'],
                // The note is what someone will read on the To-Dos list; with
                // none given the task type says enough on its own.
                'title' => ($data['task_note'] ?? null) ?: $data['task_type'],
                'due_at' => $data['task_due_at'],
                'assigned_to' => $data['assigned_to'] ?? null,
            ], $request->user());
        }

        return back()->with('success', $existing
            ? "{$customer->name} already existed — the new details were added to their record."
            : "{$customer->name} added.");
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

    public function export(CustomerFilterRequest $request): StreamedResponse
    {
        return $this->exports->streamCsv(
            // Same filtered query as the screen — previously the export ignored
            // every filter and always dumped the full customer list.
            $this->table($request)->query($request)->withCount('calls'),
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

    private function table(Request $request): DataTableService
    {
        return DataTableService::for(Customer::query()->active()->withCount('leads'))
            ->select(['id', 'name', 'mobile', 'email', 'city', 'last_activity_at', 'created_at'])
            ->searchable(['name', 'mobile', 'email'])
            ->sortable(['name', 'created_at', 'last_activity_at'])
            // A customer has no owner of their own — they belong to whoever is
            // working their enquiries, so this reads through their leads.
            ->filter('member', fn (Builder $q, $v) => $q->whereHas(
                'leads', fn (Builder $l) => $l->whereIn('assigned_to', FilterList::ids($v))
            ))
            ->filter('leads', fn (Builder $q, $v) => $v === 'with'
                ? $q->has('leads')
                : $q->doesntHave('leads'))
            ->filter('date_from', fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->filter('date_to', fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->defaultSort('id', 'desc');
    }
}
