<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\User;
use App\Services\Support\DataTableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The rows-per-page control, and the guardrails behind it. */
class PageSizeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->owner = User::factory()->owner()->create();

        for ($i = 1; $i <= 60; $i++) {
            Customer::create([
                'name' => "Contact {$i}",
                'mobile' => '98765'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'last_activity_at' => now(),
            ]);
        }
    }

    private function page(array $query = []): array
    {
        return $this->actingAs($this->owner)
            ->get('/customers?'.http_build_query($query))
            ->viewData('page')['props']['customers'];
    }

    public function test_each_offered_size_returns_that_many_rows(): void
    {
        foreach (DataTableService::PAGE_SIZES as $size) {
            $page = $this->page(['per_page' => $size]);

            $this->assertSame($size, $page['per_page'], "per_page {$size} was not honoured.");
            $this->assertCount(min($size, 60), $page['data']);
        }
    }

    public function test_the_default_is_one_the_control_can_show(): void
    {
        $page = $this->page();

        $this->assertContains(
            $page['per_page'],
            DataTableService::PAGE_SIZES,
            'The default must be one of the offered sizes, or the control renders blank.',
        );
    }

    public function test_a_size_nobody_offered_falls_back_rather_than_being_honoured(): void
    {
        // A hand-typed value is not an intent worth serving.
        $this->assertSame(25, $this->page(['per_page' => 137])['per_page']);
    }

    public function test_an_absurd_size_cannot_be_used_to_pull_the_whole_table(): void
    {
        $page = $this->page(['per_page' => 100000]);

        $this->assertSame(25, $page['per_page']);
        $this->assertLessThanOrEqual(DataTableService::MAX_PER_PAGE, count($page['data']));
    }

    public function test_the_page_size_survives_alongside_a_filter(): void
    {
        $page = $this->page(['per_page' => 10, 'search' => 'Contact 1']);

        $this->assertSame(10, $page['per_page']);
    }
}
