<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every console screen and export, loaded as an owner.
 *
 * Deliberately shallow — this is a canary for a page that stops rendering
 * because of a change made somewhere else, which unit tests will not catch.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(CrmSettingsSeeder::class);

        $this->owner = User::factory()->owner()->create();
        $this->member = User::factory()->member()->create();
    }

    public static function ownerPages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'leads' => ['/leads'],
            'customers' => ['/customers'],
            'team' => ['/team'],
            'activity log' => ['/activity'],
            'settings' => ['/settings'],
            'profile' => ['/profile'],
        ];
    }

    #[DataProvider('ownerPages')]
    public function test_an_owner_can_open_every_screen(string $path): void
    {
        $this->actingAs($this->owner)->get($path)->assertOk();
    }

    public static function exports(): array
    {
        return [
            'team' => ['/team/export'],
            'customers' => ['/customers/export'],
            'staff insights' => ['/dashboard/export/staff'],
            'customer insights' => ['/dashboard/export/customers'],
            'call records' => ['/dashboard/export/calls'],
        ];
    }

    #[DataProvider('exports')]
    public function test_every_export_streams_a_csv(string $path): void
    {
        $response = $this->actingAs($this->owner)->get($path);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        // Streamed, so the body only materialises when sent.
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_a_member_is_kept_out_of_owner_only_screens(): void
    {
        foreach (['/settings', '/activity'] as $path) {
            $this->actingAs($this->member)->get($path)->assertForbidden();
        }
    }

    public function test_a_member_can_still_reach_their_own_work(): void
    {
        foreach (['/dashboard', '/leads', '/profile'] as $path) {
            $this->actingAs($this->member)->get($path)->assertOk();
        }
    }

    public function test_the_owner_account_cannot_be_deleted(): void
    {
        $this->actingAs($this->owner)
            ->delete('/team/'.$this->owner->id)
            ->assertSessionHasErrors();

        $this->assertNotSoftDeleted($this->owner);
    }

    public function test_a_member_can_be_deleted(): void
    {
        $this->actingAs($this->owner)
            ->delete('/team/'.$this->member->id)
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($this->member);
    }
}
