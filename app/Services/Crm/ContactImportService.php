<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bulk contact import from a spreadsheet.
 *
 * Two phases, because the screen asks two separate questions. First the file is
 * checked and parked — how many rows are usable, what is wrong with the rest —
 * and only then does anyone choose what to do with them: which stage, whose
 * name, what to raise against each one. Nothing is written until that second
 * step, so a bad file costs nothing and a change of mind costs nothing.
 *
 * Every row goes through CustomerService, the same path the Create Contact form
 * uses — so a number already on file matches its owner instead of making a
 * second record, and every phone and email in the file counts toward that. An
 * import that quietly duplicated half the book would be worse than no import.
 */
class ContactImportService
{
    /**
     * A run is capped rather than streamed. Ten thousand rows in one request
     * would time out somewhere unhelpful; a clear "split the file" beats a
     * half-finished import nobody can account for.
     */
    public const MAX_ROWS = 2000;

    /** Where a checked file waits between the two steps. */
    private const PARK = 'imports';

    /**
     * Spreadsheet heading => the field it fills.
     *
     * Declared once so the template and the parser cannot drift apart — the
     * template is generated from these keys.
     *
     * The first ten are the client's own template, in their order. The rest are
     * aliases kept so a file built against the earlier format still imports
     * rather than failing with "no recognised headings" — unknown columns are
     * ignored anyway, so an alias costs nothing.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'first name' => 'first_name',
        'last name' => 'last_name',
        'primary phone' => 'phone_1',
        'secondary phone' => 'phone_2',
        'business name' => 'business_name',
        'city' => 'city',
        'address' => 'address_1',
        'email' => 'email_1',
        'website url' => 'website',
        'additional info' => 'additional_info',

        // Accepted, not advertised.
        'name' => 'name',
        'phone' => 'phone_1',
        'phone 2' => 'phone_2',
        'email 2' => 'email_2',
        'website' => 'website',
        'house no' => 'house_no',
        'address 1' => 'address_1',
        'address 2' => 'address_2',
        'source' => 'source',
    ];

    /** The columns the template offers, in the order it writes them. */
    private const TEMPLATE = [
        'first name', 'last name', 'primary phone', 'secondary phone',
        'business name', 'city', 'address', 'email', 'website url', 'additional info',
    ];

    /** Two filled-in rows, so the format is obvious without reading a manual. */
    public const SAMPLE_ROWS = [
        [
            'Asha', 'Rao', '9876543210', '9876500011',
            'Rao Weddings', 'Bengaluru', '42 MG Road, near the metro',
            'asha@example.com', 'raoweddings.example', 'Asked about December packages',
        ],
        [
            'Ravi', 'Kumar', '9812345678', '',
            '', 'Mumbai', 'Andheri West',
            'ravi@example.com', '', 'Referred by Asha',
        ],
    ];

    /** Headings in the order the template writes them. */
    public static function headings(): array
    {
        return array_map(mb_strtoupper(...), self::TEMPLATE);
    }

    public function __construct(private CustomerService $customers, private TaskService $tasks) {}

    /**
     * Step one: read the file, say what is in it, and put it aside.
     *
     * Nothing is written to the book here. The answer is only "this many rows
     * are usable, and here is what is wrong with the others".
     *
     * @return array{token: string|null, valid: int, rows: int, errors: array<int, string>}
     */
    public function inspect(UploadedFile $file, User $actor, bool $skipPhoneCheck = false): array
    {
        $read = $this->read($file->getRealPath(), $skipPhoneCheck);

        if ($read['rows'] === [] && $read['errors'] !== []) {
            return ['token' => null, 'valid' => 0, 'rows' => 0, 'errors' => $read['errors']];
        }

        // Parked under the person who uploaded it, so a token cannot be used to
        // import somebody else's file.
        $token = (string) Str::uuid();

        Storage::put(
            self::path($actor, $token),
            file_get_contents($file->getRealPath()),
        );

        return [
            'token' => $token,
            'valid' => count($read['rows']),
            'rows' => count($read['rows']) + count($read['errors']),
            'errors' => $read['errors'],
        ];
    }

    /**
     * Step two: write the checked file into the book, with the chosen settings.
     *
     * @param  array{
     *     team_id?: int|null, lead_stage_id?: int|null, lead_group_id?: int|null,
     *     tags?: array<int, int>, assign_to?: array<int, int>,
     *     update_existing?: bool, skip_phone_check?: bool,
     *     create_task?: bool, task_type?: string|null, task_title?: string|null,
     *     task_due_at?: string|null,
     * }  $settings
     * @return array{created: int, matched: int, skipped: int, errors: array<int, string>}
     */
    public function run(string $token, User $actor, array $settings = []): array
    {
        $path = self::path($actor, $token);

        if (! Storage::exists($path)) {
            return [
                'created' => 0, 'matched' => 0, 'skipped' => 0,
                'errors' => ['That upload has expired. Choose the file again.'],
            ];
        }

        $read = $this->read(Storage::path($path), (bool) ($settings['skip_phone_check'] ?? false));

        $created = 0;
        $matched = 0;
        $skipped = count($read['errors']);
        $errors = $read['errors'];

        // Round-robin, so "distributed equally" means what it says rather than
        // everything landing on whoever is first in the list.
        $owners = array_values($settings['assign_to'] ?? []);
        $seat = 0;

        foreach ($read['rows'] as $row) {
            try {
                $owner = $owners === [] ? null : $owners[$seat++ % count($owners)];

                DB::transaction(function () use ($row, $actor, $settings, $owner, &$created, &$matched): void {
                    ['customer' => $customer, 'existing' => $existing] = $this->customers->createManual([
                        ...$row['fields'],
                        'team_id' => $settings['team_id'] ?? null,
                        'created_by' => $actor->id,
                    ]);

                    /*
                     | An existing contact keeps its details unless asked
                     | otherwise. Their extra numbers and emails are attached
                     | either way — that is the duplicate rule, not an edit.
                     */
                    if ($existing && ($settings['update_existing'] ?? false)) {
                        $customer->fill(array_filter([
                            'first_name' => $row['fields']['first_name'],
                            'last_name' => $row['fields']['last_name'],
                            'business_name' => $row['fields']['business_name'] ?? null,
                            'city' => $row['fields']['city'] ?? null,
                            'website' => $row['fields']['website'] ?? null,
                            'address_1' => $row['fields']['address_1'] ?? null,
                            'additional_info' => $row['fields']['additional_info'] ?? null,
                        ]))->save();
                    }

                    if (filled($settings['tags'] ?? [])) {
                        $customer->tags()->syncWithoutDetaching($settings['tags']);
                    }

                    $this->raiseWork($customer, $row, $settings, $owner, $actor);

                    $existing ? $matched++ : $created++;
                });
            } catch (Throwable $e) {
                $skipped++;

                // Named by line so someone can find and fix it in the file.
                $errors[] = "Row {$row['line']}: ".$e->getMessage();
            }
        }

        Storage::delete($path);

        activity('customer')
            ->causedBy($actor)
            ->log("Imported contacts — {$created} added, {$matched} matched an existing contact, {$skipped} skipped");

        return [
            'created' => $created,
            'matched' => $matched,
            'skipped' => $skipped,
            // Enough to act on without burying the summary.
            'errors' => array_slice($errors, 0, 20),
        ];
    }

    /**
     * A lead, and a to-do against it, if the settings asked for either.
     *
     * The stage is what decides: without one there is no enquiry to record, and
     * a to-do with no lead has nowhere to hang.
     */
    private function raiseWork($customer, array $row, array $settings, ?int $owner, User $actor): void
    {
        if (blank($settings['lead_stage_id'] ?? null)) {
            return;
        }

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'email' => $customer->email,
            'source' => $row['fields']['source'] ?? 'import',
            'source_type' => 'CSV Upload',
            'lead_stage_id' => $settings['lead_stage_id'],
            'lead_group_id' => $settings['lead_group_id'] ?? null,
            'assigned_to' => $owner,
            // Imported by hand, so whoever ran the import has seen it.
            'viewed_at' => now(),
        ]);

        if (! ($settings['create_task'] ?? false) || blank($settings['task_type'] ?? null)) {
            return;
        }

        $this->tasks->createManual($lead, [
            'type' => $settings['task_type'],
            // The title is what someone reads on the To-Dos list; with none
            // given the task type says enough on its own.
            'title' => ($settings['task_title'] ?? null) ?: $settings['task_type'],
            'due_at' => $settings['task_due_at'] ?? null,
            'assigned_to' => $owner,
        ], $actor);
    }

    /**
     * Parse the whole file once: usable rows on one side, complaints on the other.
     *
     * @return array{rows: array<int, array{line: int, fields: array<string, mixed>}>, errors: array<int, string>}
     */
    private function read(string $path, bool $skipPhoneCheck): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ['rows' => [], 'errors' => ['That file could not be read.']];
        }

        $this->skipByteOrderMark($handle);

        $map = $this->headerMap($handle);

        if ($map === []) {
            fclose($handle);

            return [
                'rows' => [],
                'errors' => ['No recognised column headings. Download the template and match its first row.'],
            ];
        }

        $rows = [];
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($line - 1 > self::MAX_ROWS) {
                $errors[] = 'Stopped at '.self::MAX_ROWS.' rows. Split the file and import the rest separately.';
                break;
            }

            // Blank lines are normal at the end of a spreadsheet export.
            if ($row === [null] || implode('', array_map('trim', $row)) === '') {
                continue;
            }

            $data = $this->readRow($row, $map);

            /*
             | FIRST NAME + LAST NAME, or a single Name column from the older
             | format. Whichever the file carries is passed through as it stands
             | and the model keeps the other side in step — so a two-column file
             | is never re-split by guesswork on the way back out.
             */
            [$first, $last] = ($data['name'] ?? '') !== ''
                ? Customer::nameParts($data['name'])
                : [$data['first_name'] ?? '', $data['last_name'] ?? null];

            $name = trim(trim((string) $first).' '.trim((string) $last));

            if ($name === '') {
                $errors[] = "Row {$line}: no name.";

                continue;
            }

            $phones = array_values(array_filter([$data['phone_1'] ?? null, $data['phone_2'] ?? null]));

            if ($phones === []) {
                $errors[] = "Row {$line}: no phone number.";

                continue;
            }

            if (! $skipPhoneCheck && ! preg_match('/^[6-9]\d{9}$/', $phones[0])) {
                $errors[] = "Row {$line}: “{$phones[0]}” is not a 10-digit mobile number. "
                    .'Tick "skip phone number format checking" to import it anyway.';

                continue;
            }

            $rows[] = [
                'line' => $line,
                'fields' => [
                    'name' => $name,
                    'first_name' => trim((string) $first),
                    'last_name' => filled($last) ? trim((string) $last) : null,
                    'phones' => $phones,
                    'emails' => array_values(array_filter([$data['email_1'] ?? null, $data['email_2'] ?? null])),
                    'business_name' => $data['business_name'] ?? null,
                    'city' => $data['city'] ?? null,
                    'website' => $data['website'] ?? null,
                    'house_no' => $data['house_no'] ?? null,
                    'address_1' => $data['address_1'] ?? null,
                    'address_2' => $data['address_2'] ?? null,
                    'additional_info' => $data['additional_info'] ?? null,
                    'source' => $data['source'] ?? null,
                ],
            ];
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
    }

    private static function path(User $actor, string $token): string
    {
        return self::PARK."/{$actor->id}/{$token}.csv";
    }

    /**
     * Step past Excel's byte-order mark before anything reads the file.
     *
     * It has to happen here rather than on the parsed heading. Every heading in
     * the template contains a space, and a CSV writer quotes any field that
     * does — so an Excel-saved file starts BOM, then the opening quote. The BOM
     * sits *outside* the quoting, fgetcsv no longer sees an enclosed field, and
     * the first heading comes back with its quote marks still attached and
     * matches nothing. The whole first column then imports as blank.
     */
    private function skipByteOrderMark($handle): void
    {
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
    }

    /**
     * Match the file's first row to our columns.
     *
     * Case and spacing are ignored, and unknown columns are left alone rather
     * than rejected — a file carrying extra columns from somewhere else should
     * still import the parts we understand.
     *
     * @return array<int, string> column index => field
     */
    private function headerMap($handle): array
    {
        $header = fgetcsv($handle);

        if ($header === false) {
            return [];
        }

        $map = [];

        foreach ($header as $i => $heading) {
            // Excel writes a BOM on the first cell; without stripping it the
            // first column never matches and every row loses its name.
            $key = mb_strtolower(trim(preg_replace('/^\x{FEFF}/u', '', (string) $heading)));
            $key = preg_replace('/\s+/', ' ', $key);

            if (isset(self::COLUMNS[$key])) {
                $map[$i] = self::COLUMNS[$key];
            }
        }

        return $map;
    }

    /** @return array<string, string> */
    private function readRow(array $row, array $map): array
    {
        $data = [];

        foreach ($map as $i => $field) {
            $value = trim((string) ($row[$i] ?? ''));

            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        return $data;
    }
}
