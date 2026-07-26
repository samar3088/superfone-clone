<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\Crm\ContactImportService;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Contact import, in the two steps the wizard takes.
 *
 * Step one checks the file and parks it; nothing is written. Step two applies
 * the chosen settings to the rows that survived. The split is what lets a bad
 * file cost nothing.
 */
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

    /** Step one. Returns the decoded answer. */
    private function check(UploadedFile $file, array $extra = []): array
    {
        return $this->actingAs($this->owner)
            ->postJson('/customers/import/check', ['file' => $file, ...$extra])
            ->json();
    }

    /** Both steps, for the cases that only care about the outcome. */
    private function upload(UploadedFile $file, array $settings = [])
    {
        $checked = $this->check($file, array_intersect_key($settings, ['skip_phone_check' => true]));

        if (blank($checked['token'] ?? null)) {
            return $this->actingAs($this->owner)->post('/customers/import', ['token' => '']);
        }

        return $this->actingAs($this->owner)
            ->post('/customers/import', ['token' => $checked['token'], ...$settings]);
    }

    /* ── The template ─────────────────────────────────── */

    public function test_the_template_can_be_downloaded_and_describes_the_real_format(): void
    {
        $response = $this->actingAs($this->owner)->get('/customers/import/sample');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        foreach (ContactImportService::headings() as $heading) {
            $this->assertStringContainsString($heading, $csv, "The template is missing '{$heading}'.");
        }

        // The client's own column names, in their order. Quoted, because a CSV
        // writer quotes any field containing a space and all of these do.
        $this->assertStringContainsString('"FIRST NAME","LAST NAME","PRIMARY PHONE"', $csv);
        $this->assertStringContainsString('Asha', $csv, 'The template should show a filled-in row.');
    }

    public function test_the_template_imports_cleanly_into_the_importer_that_produced_it(): void
    {
        // The strongest check there is: the template we hand out actually works.
        $this->upload($this->csv(ContactImportService::SAMPLE_ROWS))->assertSessionHasNoErrors();

        $this->assertSame(2, Customer::count());
        $this->assertNotNull(Customer::where('name', 'Asha Rao')->first());
    }

    /* ── Step one: checking ───────────────────────────── */

    public function test_checking_a_file_writes_nothing(): void
    {
        $answer = $this->check($this->csv([['Asha', 'Rao', '9876500001']]));

        $this->assertSame(1, $answer['valid']);
        $this->assertNotNull($answer['token']);

        // The whole point of the first step.
        $this->assertSame(0, Customer::count());
    }

    public function test_a_row_with_no_name_is_reported_by_line_number(): void
    {
        $answer = $this->check($this->csv([
            ['Asha', 'Rao', '9876500001'],
            ['', '', '9876500009'],
        ]));

        $this->assertSame(1, $answer['valid']);
        $this->assertStringContainsString('Row 3', implode(' ', $answer['errors']));
    }

    public function test_a_row_with_no_phone_number_is_refused(): void
    {
        $answer = $this->check($this->csv([['Asha', 'Rao', '']]));

        $this->assertSame(0, $answer['valid']);
        $this->assertStringContainsString('no phone number', implode(' ', $answer['errors']));
    }

    public function test_a_malformed_number_is_refused_unless_the_check_is_skipped(): void
    {
        $file = fn () => $this->csv([['Asha', 'Rao', '12345']]);

        $strict = $this->check($file());

        $this->assertSame(0, $strict['valid']);
        $this->assertStringContainsString('not a 10-digit mobile', implode(' ', $strict['errors']));

        $relaxed = $this->check($file(), ['skip_phone_check' => '1']);

        $this->assertSame(1, $relaxed['valid']);
    }

    public function test_a_file_with_no_recognised_headings_is_refused_with_an_explanation(): void
    {
        $answer = $this->check($this->csv([['x', 'y']], headings: ['Column A', 'Column B']));

        $this->assertNull($answer['token']);
        $this->assertStringContainsString('No recognised column headings', implode(' ', $answer['errors']));
    }

    public function test_the_excel_byte_order_mark_does_not_break_the_first_column(): void
    {
        // Excel writes a BOM; without stripping it the name column never
        // matches and every row is rejected as nameless.
        $this->upload($this->csv([['Asha', 'Rao', '9876500001']], bom: true));

        $this->assertSame(1, Customer::count());
        $this->assertSame('Asha Rao', Customer::sole()->name);
    }

    public function test_headings_are_matched_regardless_of_case_and_spacing(): void
    {
        $this->upload($this->csv(
            [['Asha', 'Rao', '9876500001']],
            headings: ['  first NAME  ', 'Last Name', 'PRIMARY  PHONE'],
        ));

        $this->assertSame(1, Customer::count());
    }

    public function test_a_file_in_the_older_single_name_format_still_imports(): void
    {
        // Aliases are kept so a sheet built against the previous template does
        // not fail with "no recognised headings".
        $this->upload($this->csv(
            [['Asha Rao', '9876500001', 'asha@example.com']],
            headings: ['Name', 'Phone', 'Email'],
        ));

        $this->assertSame('Asha Rao', Customer::sole()->name);
    }

    public function test_unknown_columns_are_ignored_rather_than_rejected(): void
    {
        $this->upload($this->csv(
            [['Asha', 'Rao', '9876500001', 'something we do not know about']],
            headings: ['First Name', 'Last Name', 'Primary Phone', 'Star sign'],
        ));

        $this->assertSame(1, Customer::count());
    }

    /* ── Step two: importing ──────────────────────────── */

    public function test_rows_become_contacts_with_all_their_numbers(): void
    {
        $this->upload($this->csv([
            ['Asha', 'Rao', '9876500001', '9876500002', 'Rao Weddings', 'Bengaluru',
                '42 MG Road', 'asha@example.com', 'raoweddings.example', 'Notes here'],
        ]));

        $customer = Customer::sole();

        $this->assertSame('Asha Rao', $customer->name);
        $this->assertSame('9876500001', $customer->mobile);
        $this->assertSame(2, $customer->phones()->count());
        $this->assertSame('Rao Weddings', $customer->business_name);
        $this->assertSame($this->owner->id, $customer->created_by);
    }

    public function test_a_number_already_on_file_matches_instead_of_duplicating(): void
    {
        Customer::create(['name' => 'Asha', 'mobile' => '9876500001', 'last_activity_at' => now()])
            ->channels()->create(['type' => 'phone', 'value' => '9876500001', 'is_primary' => true]);

        $this->upload($this->csv([['Asha', 'Rao', '9876500001']]));

        // The whole point: an import must not double the book.
        $this->assertSame(1, Customer::count());

        // And it is left alone unless asked otherwise.
        $this->assertSame('Asha', Customer::sole()->name);
    }

    public function test_an_existing_contact_is_rewritten_only_when_asked(): void
    {
        Customer::create(['name' => 'Asha', 'mobile' => '9876500001', 'last_activity_at' => now()])
            ->channels()->create(['type' => 'phone', 'value' => '9876500001', 'is_primary' => true]);

        $this->upload(
            $this->csv([['Asha', 'Rao', '9876500001', '', 'Rao Weddings']]),
            ['update_existing' => true],
        );

        $this->assertSame(1, Customer::count());
        $this->assertSame('Asha Rao', Customer::sole()->name);
        $this->assertSame('Rao Weddings', Customer::sole()->business_name);
    }

    public function test_a_stage_turns_each_row_into_a_lead(): void
    {
        $stage = LeadStage::where('type', 'INITIAL')->firstOrFail();

        $this->upload($this->csv([['Asha', 'Rao', '9876500001']]), [
            'lead_stage_id' => $stage->id,
        ])->assertSessionHasNoErrors();

        $lead = Lead::sole();

        $this->assertSame($stage->id, $lead->lead_stage_id);
        $this->assertSame('CSV Upload', $lead->source_type);
    }

    public function test_without_a_stage_the_rows_are_contacts_and_nothing_else(): void
    {
        $this->upload($this->csv([['Asha', 'Rao', '9876500001']]));

        $this->assertSame(1, Customer::count());
        $this->assertSame(0, Lead::count());
    }

    public function test_contacts_are_shared_out_evenly_between_the_chosen_members(): void
    {
        $one = User::factory()->member()->create();
        $two = User::factory()->member()->create();

        $this->upload($this->csv([
            ['A', 'One', '9876500001'],
            ['B', 'Two', '9876500002'],
            ['C', 'Three', '9876500003'],
            ['D', 'Four', '9876500004'],
        ]), [
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
            'assign_to' => [$one->id, $two->id],
        ])->assertSessionHasNoErrors();

        // Round-robin, not everything landing on whoever is first in the list.
        $this->assertSame(2, Lead::where('assigned_to', $one->id)->count());
        $this->assertSame(2, Lead::where('assigned_to', $two->id)->count());
    }

    public function test_tags_are_attached_to_every_imported_contact(): void
    {
        $tag = Tag::firstOrCreate(['name' => 'Imported'], ['color' => '#000000']);

        $this->upload($this->csv([['Asha', 'Rao', '9876500001']]), ['tags' => [$tag->id]])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Customer::sole()->tags->contains($tag));
    }

    public function test_a_to_do_can_be_raised_against_every_imported_lead(): void
    {
        $this->upload($this->csv([['Asha', 'Rao', '9876500001']]), [
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
            'assign_to' => [User::factory()->member()->create()->id],
            'create_task' => true,
            'task_type' => 'FIRST CALL',
            'task_title' => 'Ring the new list',
            'task_due_at' => now()->addDay()->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Ring the new list', Task::sole()->title);
    }

    public function test_asking_for_a_to_do_without_a_stage_is_refused_with_a_reason(): void
    {
        // A to-do hangs off a lead, and no stage means no lead. Silently
        // raising nothing would look like the setting had been ignored.
        $this->upload($this->csv([['Asha', 'Rao', '9876500001']]), [
            'create_task' => true,
            'task_type' => 'FIRST CALL',
            'task_due_at' => now()->addDay()->toDateTimeString(),
        ])->assertSessionHasErrors('lead_stage_id');

        $this->assertSame(0, Task::count());
    }

    public function test_the_parked_file_is_cleared_once_it_has_been_imported(): void
    {
        $checked = $this->check($this->csv([['Asha', 'Rao', '9876500001']]));

        $path = "imports/{$this->owner->id}/{$checked['token']}.csv";
        $this->assertTrue(Storage::exists($path));

        $this->actingAs($this->owner)->post('/customers/import', ['token' => $checked['token']]);

        $this->assertFalse(Storage::exists($path), 'The upload was left behind on disk.');
    }

    /* ── Guardrails ───────────────────────────────────── */

    public function test_one_person_cannot_import_another_persons_upload(): void
    {
        $checked = $this->check($this->csv([['Asha', 'Rao', '9876500001']]));

        $this->actingAs(User::factory()->owner()->create())
            ->post('/customers/import', ['token' => $checked['token']]);

        // The token is resolved under whoever uploaded the file, so it finds
        // nothing for anybody else.
        $this->assertSame(0, Customer::count());
    }

    public function test_an_unknown_token_is_refused_rather_than_importing_nothing_silently(): void
    {
        $this->actingAs($this->owner)
            ->post('/customers/import', ['token' => '11111111-1111-1111-1111-111111111111'])
            ->assertSessionHas('importErrors');

        $this->assertSame(0, Customer::count());
    }

    public function test_a_non_csv_upload_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/customers/import/check', [
                'file' => UploadedFile::fake()->create('contacts.pdf', 10),
            ])
            ->assertStatus(422);
    }

    public function test_a_guest_cannot_import(): void
    {
        $this->post('/customers/import/check', ['file' => $this->csv([])])->assertRedirect('/login');
        $this->post('/customers/import', ['token' => 'x'])->assertRedirect('/login');

        $this->assertSame(0, Customer::count());
    }

    public function test_a_guest_cannot_download_the_template(): void
    {
        $this->get('/customers/import/sample')->assertRedirect('/login');
    }
}
