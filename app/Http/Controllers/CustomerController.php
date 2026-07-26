<?php

namespace App\Http\Controllers;

use App\Http\Requests\Crm\CustomerFilterRequest;
use App\Http\Requests\Crm\ImportContactsRequest;
use App\Http\Requests\Crm\RunImportRequest;
use App\Http\Requests\Crm\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\Crm\ContactImportService;
use App\Services\Crm\CustomerService;
use App\Services\Crm\NoteService;
use App\Services\Crm\TaskService;
use App\Services\Support\DataTableService;
use App\Services\Support\ExportService;
use App\Support\ContactColumns;
use App\Support\FilterList;
use App\Support\LeadProviders;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
    private const FILTER_KEYS = [
        'search', 'member', 'leads', 'date_from', 'date_to',
        'team', 'tags', 'stage', 'group', 'creator',
    ];

    public function index(CustomerFilterRequest $request): Response
    {
        return Inertia::render('customers/index', [
            'customers' => $this->table($request)->paginate($request),
            'filters' => $request->only([...self::FILTER_KEYS, 'sort', 'direction', 'per_page']),
            'members' => User::role([Roles::OWNER, Roles::MEMBER])->orderBy('name')->get(['id', 'name']),
            // Options for the Create Contact form.
            'options' => [
                // Shown as "number, NAME" the way the organisation is
                // identified elsewhere; the number appears once one exists.
                'teams' => Team::orderBy('id')->get(['id', 'name', 'virtual_number']),
                'tags' => Tag::where('is_hidden', false)->orderBy('name')
                    ->get(['id', 'name', 'color', 'emoji']),
                // Only people who have actually added a contact — a list of
                // everyone would mostly be names that can never match.
                'creators' => User::whereIn('id', Customer::whereNotNull('created_by')
                    ->distinct()->pluck('created_by'))
                    ->orderBy('name')->get(['id', 'name']),
                'sourceTypes' => LeadProviders::sourceTypes(),
                'todoTypes' => LeadProviders::todoTypes(),
                'stages' => LeadStage::where('is_active', true)->orderBy('sequence')
                    ->get(['id', 'name', 'emoji']),
                'groups' => LeadGroup::where('is_active', true)->get(['id', 'name']),
                // Offered by the Download dialog, in the order a file writes them.
                'downloadColumns' => collect(ContactColumns::CHOICES)
                    ->map(fn (string $heading, string $key) => compact('key', 'heading'))
                    ->values(),
                'downloadDefaults' => ContactColumns::DEFAULTS,
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

        ['customer' => $customer, 'existing' => $existing] = $this->customers->createManual(
            [...$data, 'created_by' => $request->user()->id],
        );

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

    /**
     * A filled-in template, generated rather than kept as a file on disk.
     *
     * Built from the same column map the parser uses, so the sample can never
     * describe a format the importer no longer accepts.
     */
    public function importSample(): StreamedResponse
    {
        $filename = 'vvt-contacts-template.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // Excel needs the BOM to read accented names as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ContactImportService::headings());

            foreach (ContactImportService::SAMPLE_ROWS as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Step one of the import: check the file and park it.
     *
     * Answers as JSON rather than as a redirect, because the wizard stays on
     * the same modal and only moves its own step forward — a page round-trip
     * would throw away the choices already made on it.
     */
    public function importCheck(ImportContactsRequest $request, ContactImportService $importer): JsonResponse
    {
        return response()->json($importer->inspect(
            $request->file('file'),
            $request->user(),
            $request->boolean('skip_phone_check'),
        ));
    }

    /** Step two: write the checked file into the book. */
    public function import(RunImportRequest $request, ContactImportService $importer): RedirectResponse
    {
        $data = $request->validated();

        $result = $importer->run($data['token'], $request->user(), [
            'team_id' => $data['team_id'] ?? null,
            'lead_stage_id' => $data['lead_stage_id'] ?? null,
            'lead_group_id' => $data['lead_group_id'] ?? null,
            'tags' => $data['tags'] ?? [],
            'assign_to' => $data['assign_to'] ?? [],
            'update_existing' => $data['update_existing'] ?? false,
            'skip_phone_check' => $data['skip_phone_check'] ?? false,
            'create_task' => $data['create_task'] ?? false,
            'task_type' => $data['task_type'] ?? null,
            'task_title' => $data['task_title'] ?? null,
            'task_due_at' => $data['task_due_at'] ?? null,
        ]);

        $summary = "{$result['created']} added";

        if ($result['matched'] > 0) {
            $summary .= ", {$result['matched']} already on file";
        }

        if ($result['skipped'] > 0) {
            $summary .= ", {$result['skipped']} skipped";
        }

        return back()
            ->with($result['created'] > 0 ? 'success' : 'error', "Import finished — {$summary}.")
            ->with('importErrors', $result['errors'])
            ->with('importSummary', $result);
    }

    /** One customer with every lead they have ever raised. */
    public function show(Customer $customer, NoteService $notes): Response
    {
        return Inertia::render('customers/show', [
            'customer' => $customer->loadCount(['leads', 'calls', 'notes']),
            'leads' => $this->customers->leadHistory($customer),
            'duplicates' => $this->customers->findPotentialDuplicates($customer),
            'notes' => $notes->timeline($customer),
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
        // A merge soft-deletes the duplicate and moves its leads and calls, so
        // it is a deletion in everything but name — owner only.
        abort_unless($request->user()?->can(Permissions::CUSTOMER_MERGE), 403);

        $data = $request->validate([
            'duplicate_ids' => ['required', 'array', 'min:1'],
            'duplicate_ids.*' => ['integer', 'exists:customers,id'],
        ]);

        $this->customers->merge($customer, $data['duplicate_ids']);

        return back()->with('success', 'Customers merged. All their leads now sit under this record.');
    }

    /**
     * Download the contact book, or the part of it currently filtered.
     *
     * Which columns come out is the caller's choice; the order is always the
     * canonical one, so two downloads of the same tick-boxes are the same file.
     */
    public function export(CustomerFilterRequest $request): StreamedResponse
    {
        $columns = ContactColumns::resolve(
            FilterList::parse((string) $request->string('columns')),
        );

        return $this->exports->streamCsv(
            // Same filtered query as the screen — previously the export ignored
            // every filter and always dumped the full customer list.
            $this->table($request)->query($request)
                ->withCount('calls')
                // Second numbers live in customer_channels, so without this the
                // SECONDARY PHONE column would silently come out blank.
                ->with('channels:id,customer_id,type,value,is_primary'),
            ContactColumns::headings($columns),
            fn (Customer $c) => ContactColumns::row($c, $columns),
            'contacts-'.now()->format('Y-m-d-Hi').'.csv',
        );
    }

    private function table(Request $request): DataTableService
    {
        return DataTableService::for(
            Customer::query()->active()->withCount(['leads', 'notes'])->with([
                'tags:id,name,color,emoji',
                'team:id,name',
                'creator:id,name',
                /*
                 | One extra query for the page, not one per row.
                 |
                 | Loaded whole rather than with a column list: latestOfMany
                 | joins leads to itself, so an unqualified "customer_id" in a
                 | select is ambiguous and the query dies.
                 */
                'latestLead',
                'latestLead.stage:id,name,emoji',
                'latestLead.assignee:id,name',
            ])
        )
            ->select(['id', 'team_id', 'created_by', 'name', 'mobile', 'email', 'city',
                'business_name', 'additional_info', 'last_activity_at', 'created_at'])
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

            ->filter('team', fn (Builder $q, $v) => $q->whereIn('team_id', FilterList::ids($v)))
            ->filter('creator', fn (Builder $q, $v) => $q->whereIn('created_by', FilterList::ids($v)))
            ->filter('tags', fn (Builder $q, $v) => $q->whereHas(
                'tags', fn (Builder $t) => $t->whereIn('tags.id', FilterList::ids($v))
            ))

            /*
             | Stage and group live on the lead, not the contact. A contact
             | matches if any of their leads does — which is what someone means
             | by "show me contacts at Quotation Sent".
             */
            ->filter('stage', fn (Builder $q, $v) => $q->whereHas(
                'leads', fn (Builder $l) => $l->whereIn('lead_stage_id', FilterList::ids($v))
            ))
            ->filter('group', fn (Builder $q, $v) => $q->whereHas(
                'leads', fn (Builder $l) => $l->whereIn('lead_group_id', FilterList::ids($v))
            ))
            ->filter('date_from', fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->filter('date_to', fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->defaultSort('id', 'desc');
    }
}
