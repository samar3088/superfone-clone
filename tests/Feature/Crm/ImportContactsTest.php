<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\User;
use App\Services\Crm\ContactImportService;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportContactsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(CrmSettingsSeeder::class);
        $this->owner = User::factory()->owner()->create();
    }

    /** Build a CSV the way a spreadsheet would write one. */
    private function csv(array $rows, ?array $headings = null, bool $bom = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        $out = fopen($path, 'w');

        if ($bom) {
            fwrite($out, "\xEF\xBB\xBF");
        }

        fputcsv($out, $headings ?? ContactImportService::headings());

        foreach ($rows as $row) {
            fputcsv($out, $row);
        }

        fclose($out);

        return new UploadedFile($path, 'contacts.csv', 'text/csv', null, true);
    }

    private function upload(UploadedFile $file)
    {
        return $this->actingAs($this->owner)->post('/customers/import', ['file' => $file]);
    }

    /* ── The sample ───────────────────────────────────── */

    public function test_the_sample_can_be_downloaded_and_describes_the_real_format(): void
    {
        $response = $this->actingAs($this->owner)->get('/customers/import/sample');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        // Every heading the parser understands must appear in the sample.
        foreach (ContactImportService::headings() as $heading) {
            $this->assertStringContainsString($heading, $csv, "The sample is missing '{$heading}'.");
        }

        $this->assertStringContainsString('Asha Rao', $csv, 'The sample should show a filled-in row.');
    }

    public function test_the_sample_imports_cleanly_into_the_importer_that_produced_it(): void
    {
        // The strongest check there is: the template we hand out actually works.
        $csv = $this->csv(ContactImportService::SAMPLE_ROWS);

        $this->upload($csv)->assertSessionHasNoErrors();

        $this->assertSame(2, Customer::count());
        $this->assertNotNull(Customer::where('name', 'Asha Rao')->first());
    }

    /* ── Importing ────────────────────────────────────── */

    public function test_rows_become_contacts_with_all_their_numbers(): void
    {
        $this->upload($this->csv([
            ['Asha Rao', '9876500001', '9876500002', 'asha@example.com', 'asha2@example.com',
                'Rao Weddings', 'Bengaluru', '', '', '', '', 'Notes here', 'Walk-in'],
        ]));

        $customer = Customer::sole();

        $this->assertSame('Asha Rao', $customer->name);
        $this->assertSame('9876500001', $customer->mobile);
        $this->assertSame(2, $customer->phones()->count());
        $this->assertSame(2, $customer->emails()->count());
        $this->assertSame('Rao Weddings', $customer->business_name);
        $this->assertSame($this->owner->id, $customer->created_by);
    }

    public function test_a_number_already_on_file_matches_instead_of_duplicating(): void
    {
        Customer::create(['name' => 'Asha', 'mobile' => '9876500001', 'last_activity_at' => now()])
            ->channels()->create(['type' => 'phone', 'value' => '9876500001', 'is_primary' => true]);

        $this->upload($this->csv([
            ['Asha Rao', '9876500001', '', '', '', '', '', '', '', '', '', '', ''],
        ]));

        // The whole point: an import must not double the book.
        $this->assertSame(1, Customer::count());
    }

    public function test_the_excel_byte_order_mark_does_not_break_the_first_column(): void
    {
        // Excel writes a BOM; without stripping it the name column never
        // matches and every row is rejected as nameless.
        $this->upload($this->csv([
            ['Asha Rao', '9876500001', '', '', '', '', '', '', '', '', '', '', ''],
        ], bom: true));

        $this->assertSame(1, Customer::count());
        $this->assertSame('Asha Rao', Customer::sole()->name);
    }

    public function test_headings_are_matched_regardless_of_case_and_spacing(): void
    {
        $this->upload($this->csv(
            [['Asha Rao', '9876500001']],
            headings: ['  NAME  ', 'Phone'],
        ));

        $this->assertSame(1, Customer::count());
    }

    public function test_unknown_columns_are_ignored_rather_than_rejected(): void
    {
        $this->upload($this->csv(
            [['Asha Rao', '9876500001', 'something we do not know about']],
            headings: ['Name', 'Phone', 'Star sign'],
        ));

        $this->assertSame(1, Customer::count());
    }

    public function test_a_row_with_no_name_is_reported_by_line_number(): void
    {
        $this->upload($this->csv([
            ['Asha Rao', '9876500001', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '9876500009', '', '', '', '', '', '', '', '', '', '', ''],
        ]));

        $this->assertSame(1, Customer::count());
        $this->assertStringContainsString('Row 3', implode(' ', session('importErrors') ?? []));
    }

    public function test_a_file_with_no_recognised_headings_is_refused_with_an_explanation(): void
    {
        $this->upload($this->csv([['x', 'y']], headings: ['Column A', 'Column B']));

        $this->assertSame(0, Customer::count());
        $this->assertStringContainsString(
            'No recognised column headings',
            implode(' ', session('importErrors') ?? []),
        );
    }

    public function test_blank_trailing_rows_are_skipped_silently(): void
    {
        $this->upload($this->csv([
            ['Asha Rao', '9876500001', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', ''],
        ]));

        $this->assertSame(1, Customer::count());
        $this->assertEmpty(session('importErrors') ?? []);
    }

    /* ── Guardrails ───────────────────────────────────── */

    public function test_a_non_csv_upload_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post('/customers/import', ['file' => UploadedFile::fake()->create('contacts.pdf', 10)])
            ->assertSessionHasErrors('file');
    }

    public function test_a_guest_cannot_import(): void
    {
        $this->post('/customers/import', ['file' => $this->csv([])])->assertRedirect('/login');

        $this->assertSame(0, Customer::count());
    }

    public function test_a_guest_cannot_download_the_sample(): void
    {
        $this->get('/customers/import/sample')->assertRedirect('/login');
    }
}
