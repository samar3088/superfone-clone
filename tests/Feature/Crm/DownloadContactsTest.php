<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\Crm\ContactImportService;
use App\Support\ContactColumns;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Downloading the contact book.
 *
 * The property worth holding onto is the round trip: a download of the template
 * columns must import straight back. That is how a bulk update is actually
 * done, and it only stays true if the two column lists are spelled identically.
 */
class DownloadContactsTest extends TestCase
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

    private function asha(): Customer
    {
        $customer = Customer::create([
            'name' => 'Asha Rao',
            'mobile' => '9876500001',
            'email' => 'asha@example.com',
            'city' => 'Bengaluru',
            'business_name' => 'Rao Weddings',
            'last_activity_at' => now(),
        ]);

        // The primary is created by the model itself; this is the second
        // number, which is what the SECONDARY PHONE column reads.
        $customer->channels()->create([
            'type' => 'phone', 'value' => '9876500002', 'is_primary' => false,
        ]);

        return $customer;
    }

    private function download(array $query = []): string
    {
        return $this->actingAs($this->owner)
            ->get('/customers/export?'.http_build_query($query))
            ->assertOk()
            ->streamedContent();
    }

    /* ── The round trip ───────────────────────────────── */

    public function test_the_download_columns_are_spelled_exactly_like_the_import_template(): void
    {
        $template = ContactImportService::headings();
        $download = array_slice(array_values(ContactColumns::CHOICES), 0, count($template));

        // Not "similar" — identical, and in the same order. A download that
        // said "PHONE" where the importer wants "PRIMARY PHONE" would import
        // as a book full of nameless, numberless contacts.
        $this->assertSame($template, $download);
    }

    public function test_a_download_of_the_template_columns_imports_straight_back(): void
    {
        $this->asha();

        $csv = $this->download([
            'columns' => implode(',', array_slice(array_keys(ContactColumns::CHOICES), 0, 10)),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'dl').'.csv';
        file_put_contents($path, $csv);

        // Straight back into the importer that the download claims to match.
        $checked = $this->actingAs($this->owner)
            ->postJson('/customers/import/check', [
                'file' => new UploadedFile($path, 'contacts.csv', 'text/csv', null, true),
            ])
            ->json();

        $this->assertSame(1, $checked['valid'], 'The download did not survive its own importer.');
        $this->assertSame([], $checked['errors']);
    }

    /* ── Columns ──────────────────────────────────────── */

    public function test_only_the_chosen_columns_come_out(): void
    {
        $this->asha();

        $csv = $this->download(['columns' => 'first_name,primary_phone']);

        $this->assertStringContainsString('"FIRST NAME","PRIMARY PHONE"', $csv);
        $this->assertStringNotContainsString('CITY', $csv);
        $this->assertStringNotContainsString('Bengaluru', $csv);
    }

    public function test_columns_come_out_in_the_canonical_order_whatever_order_they_were_asked_for(): void
    {
        $this->asha();

        // Same tick-boxes, so the same file — otherwise two people comparing
        // their downloads would find the columns shuffled.
        $this->assertStringContainsString(
            '"FIRST NAME","PRIMARY PHONE"',
            $this->download(['columns' => 'primary_phone,first_name']),
        );
    }

    public function test_a_name_nobody_split_comes_out_whole_in_the_first_column(): void
    {
        Customer::create(['name' => 'Asha Devi Rao', 'mobile' => '9876500003', 'last_activity_at' => now()]);

        $csv = $this->download(['columns' => 'first_name,last_name']);

        // Never carved up on the way out. Nobody stated where the surname
        // begins, so the download does not claim to know either.
        $this->assertStringContainsString('"Asha Devi Rao",', $csv);
    }

    public function test_a_stated_split_comes_out_in_both_columns(): void
    {
        Customer::create([
            'first_name' => 'Asha', 'last_name' => 'Rao',
            'mobile' => '9876500004', 'last_activity_at' => now(),
        ]);

        $this->assertStringContainsString(
            'Asha,Rao',
            $this->download(['columns' => 'first_name,last_name']),
        );
    }

    public function test_a_second_number_comes_out_in_the_secondary_column(): void
    {
        $this->asha();

        $csv = $this->download(['columns' => 'primary_phone,secondary_phone']);

        // Extra numbers live in customer_channels; without the eager load this
        // column would silently be blank on every row.
        $this->assertStringContainsString('9876500001,9876500002', $csv);
    }

    public function test_asking_for_nothing_recognisable_falls_back_rather_than_producing_an_empty_file(): void
    {
        $this->asha();

        $csv = $this->download(['columns' => 'star_sign,favourite_colour']);

        $this->assertStringContainsString('"FIRST NAME"', $csv);
        $this->assertStringContainsString('Asha', $csv);
    }

    /* ── Filters ──────────────────────────────────────── */

    public function test_the_download_carries_the_filters_applied_to_the_list(): void
    {
        $this->asha();
        Customer::create(['name' => 'Ravi Kumar', 'mobile' => '9812345678', 'last_activity_at' => now()]);

        $csv = $this->download(['search' => 'Asha', 'columns' => 'first_name']);

        $this->assertStringContainsString('Asha', $csv);
        $this->assertStringNotContainsString('Ravi', $csv);
    }

    public function test_the_download_and_the_list_agree_on_every_filter(): void
    {
        $tag = Tag::firstOrCreate(['name' => 'Imported'], ['color' => '#000000']);
        $stage = LeadStage::where('type', 'INITIAL')->firstOrFail();
        $member = User::factory()->member()->create();
        $team = Team::firstOrCreate(['name' => 'Sales'], ['status' => 'active']);

        $keep = $this->asha();
        $keep->tags()->attach($tag);
        $keep->update(['team_id' => $team->id]);

        Lead::create([
            'customer_id' => $keep->id, 'name' => $keep->name, 'mobile' => $keep->mobile,
            'source' => 'facebook', 'lead_stage_id' => $stage->id, 'assigned_to' => $member->id,
        ]);

        // Somebody the filters should exclude every time.
        Customer::create(['name' => 'Ravi Kumar', 'mobile' => '9812345678', 'last_activity_at' => now()]);

        /*
         | The guarantee worth holding: narrow the list one way and the download
         | the same way, and they describe the same people. Both read the same
         | request keys through the same query builder, and this proves it for
         | each filter rather than trusting that they still do.
         */
        $cases = [
            'team' => (string) $team->id,
            'member' => (string) $member->id,
            'tags' => (string) $tag->id,
            'stage' => (string) $stage->id,
            'search' => 'Asha',
        ];

        foreach ($cases as $key => $value) {
            $onScreen = collect(
                $this->actingAs($this->owner)
                    ->get('/customers?'.http_build_query([$key => $value]))
                    ->viewData('page')['props']['customers']['data']
            )->pluck('name')->sort()->values()->all();

            $csv = $this->download([$key => $value, 'columns' => 'first_name']);

            $this->assertSame(['Asha Rao'], $onScreen, "The list disagrees on '{$key}'.");

            foreach ($onScreen as $name) {
                $this->assertStringContainsString($name, $csv, "The download is missing '{$name}' when filtering by '{$key}'.");
            }

            $this->assertStringNotContainsString('Ravi', $csv, "The download ignored '{$key}'.");
        }
    }

    public function test_a_download_carries_no_cap_on_the_date_range(): void
    {
        $this->asha();

        // Deliberately wider than a month. Exports stream row by row, so the
        // size of the window costs nothing and there is no limit to hit.
        $csv = $this->download([
            'date_from' => '2020-01-01',
            'date_to' => '2030-12-31',
            'columns' => 'first_name',
        ]);

        $this->assertStringContainsString('Asha', $csv);
    }

    public function test_every_column_the_client_asked_for_is_offered(): void
    {
        $expected = [
            'FIRST NAME', 'LAST NAME', 'PRIMARY PHONE', 'SECONDARY PHONE', 'BUSINESS NAME',
            'CITY', 'ADDRESS', 'EMAIL', 'WEBSITE URL', 'ADDITIONAL INFO',
            'DATE CREATED', 'LEAD STAGE', 'TAGS', 'ASSIGNED TO', 'SOURCE', 'NOTES',
        ];

        $offered = array_values(ContactColumns::CHOICES);

        foreach ($expected as $heading) {
            $this->assertContains($heading, $offered, "The download cannot produce '{$heading}'.");
        }
    }

    public function test_the_lead_and_note_columns_actually_carry_their_values(): void
    {
        $customer = $this->asha();
        $customer->tags()->attach(Tag::firstOrCreate(['name' => 'VIP'], ['color' => '#7c3aed']));
        $customer->noteEntries()->create([
            'user_id' => $this->owner->id, 'body' => 'Prefers evenings.',
        ]);

        Lead::create([
            'customer_id' => $customer->id, 'name' => $customer->name, 'mobile' => $customer->mobile,
            'source' => 'facebook',
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
            'assigned_to' => User::factory()->member()->create(['name' => 'Aarav'])->id,
        ]);

        $csv = $this->download(['columns' => 'lead_stage,tags,assigned_to,source,notes']);

        // Each of these needs a relation loaded; a missing eager load shows up
        // here as a silently empty column rather than an error.
        $this->assertStringContainsString('New Inquiry', $csv);
        $this->assertStringContainsString('VIP', $csv);
        $this->assertStringContainsString('Aarav', $csv);
        $this->assertStringContainsString('facebook', $csv);
        $this->assertStringContainsString('Prefers evenings.', $csv);
    }

    public function test_the_file_is_never_served_from_a_cache(): void
    {
        $response = $this->actingAs($this->owner)->get('/customers/export');

        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));
    }

    public function test_a_guest_cannot_download_the_book(): void
    {
        $this->get('/customers/export')->assertRedirect('/login');
    }
}
