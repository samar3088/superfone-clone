<?php

namespace App\Services\Crm;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bulk contact import from a spreadsheet.
 *
 * Every row goes through CustomerService, the same path the Create Contact
 * form uses — so a number already on file matches its owner instead of making
 * a second record, and every phone and email in the file counts toward that.
 * An import that quietly duplicated half the book would be worse than no
 * import at all.
 */
class ContactImportService
{
    /**
     * A run is capped rather than streamed. Ten thousand rows in one request
     * would time out somewhere unhelpful; a clear "split the file" beats a
     * half-finished import nobody can account for.
     */
    public const MAX_ROWS = 2000;

    /**
     * Spreadsheet heading => the field it fills.
     *
     * Declared once so the sample file and the parser cannot drift apart —
     * the sample is generated from these keys.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'name' => 'name',
        'phone' => 'phone_1',
        'phone 2' => 'phone_2',
        'email' => 'email_1',
        'email 2' => 'email_2',
        'business name' => 'business_name',
        'city' => 'city',
        'website' => 'website',
        'house no' => 'house_no',
        'address 1' => 'address_1',
        'address 2' => 'address_2',
        'additional info' => 'additional_info',
        'source' => 'source',
    ];

    /** Two filled-in rows, so the format is obvious without reading a manual. */
    public const SAMPLE_ROWS = [
        [
            'Asha Rao', '9876543210', '9876500011', 'asha@example.com', 'asha.work@example.com',
            'Rao Weddings', 'Bengaluru', 'raoweddings.example', '42', 'MG Road', 'Near the metro',
            'Asked about December packages', 'Walk-in',
        ],
        [
            'Ravi Kumar', '9812345678', '', 'ravi@example.com', '',
            '', 'Mumbai', '', '', 'Andheri West', '',
            'Referred by Asha', 'Referral',
        ],
    ];

    /** Headings in the order the sample writes them. */
    public static function headings(): array
    {
        return array_map(
            fn (string $h) => ucfirst($h),
            array_keys(self::COLUMNS),
        );
    }

    public function __construct(private CustomerService $customers) {}

    /**
     * @return array{created: int, matched: int, skipped: int, errors: array<int, string>}
     */
    public function import(UploadedFile $file, User $actor, ?int $teamId = null): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return ['created' => 0, 'matched' => 0, 'skipped' => 0, 'errors' => ['That file could not be read.']];
        }

        $map = $this->headerMap($handle);

        if ($map === []) {
            fclose($handle);

            return [
                'created' => 0, 'matched' => 0, 'skipped' => 0,
                'errors' => ['No recognised column headings. Download the sample and match its first row.'],
            ];
        }

        $created = 0;
        $matched = 0;
        $skipped = 0;
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

            if (blank($data['name'] ?? null)) {
                $skipped++;
                $errors[] = "Row {$line}: no name.";

                continue;
            }

            try {
                $result = DB::transaction(fn () => $this->customers->createManual([
                    'name' => $data['name'],
                    'phones' => array_filter([$data['phone_1'] ?? null, $data['phone_2'] ?? null]),
                    'emails' => array_filter([$data['email_1'] ?? null, $data['email_2'] ?? null]),
                    'business_name' => $data['business_name'] ?? null,
                    'city' => $data['city'] ?? null,
                    'website' => $data['website'] ?? null,
                    'house_no' => $data['house_no'] ?? null,
                    'address_1' => $data['address_1'] ?? null,
                    'address_2' => $data['address_2'] ?? null,
                    'additional_info' => $data['additional_info'] ?? null,
                    'team_id' => $teamId,
                    'created_by' => $actor->id,
                ]));

                $result['existing'] ? $matched++ : $created++;
            } catch (Throwable $e) {
                $skipped++;

                // Named by line so someone can find and fix it in the file.
                $errors[] = "Row {$line}: ".$e->getMessage();
            }
        }

        fclose($handle);

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
