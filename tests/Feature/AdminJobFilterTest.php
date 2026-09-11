<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Services\OptionListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adding a filter used to mean a code change and a deploy: the groups lived
 * in `config/options.php`, and Reference data could only edit the *values*
 * under an existing one. These cover the round trip — an operator adds a
 * group, the app is served it, and `/jobs` filters on it.
 */
class AdminJobFilterTest extends TestCase
{
    use RefreshDatabase;
    use RefreshDatabase;

    public function test_the_shipped_filters_are_listed_before_anybody_edits_them(): void
    {
        $this->actingAsAdmin();

        $body = $this->getJson("{$this->api}/admin/job-filters")->assertOk()->json('data');

        $this->assertFalse($body['is_overridden']);
        $this->assertSame(
            ['experience', 'salary', 'job_type', 'shift', 'city'],
            array_column($body['groups'], 'key'),
        );

        // The form's choices come from the server, so the whitelist has one
        // definition rather than a copy in the panel.
        $this->assertSame(OptionListService::FILTER_COLUMNS, $body['columns']);
        $this->assertSame(OptionListService::FILTER_TYPES, $body['types']);
    }

    public function test_a_new_filter_reaches_the_app_and_actually_filters(): void
    {
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/job-filters", $this->roleFilter())
            ->assertCreated();

        // Served to the app, with its chips resolved from the named list.
        $group = collect($this->getJson("{$this->api}/config/options")->json('data.job_filters'))
            ->firstWhere('key', 'role');

        $this->assertNotNull($group);
        $this->assertSame('role', $group['param']);
        $this->assertContains('Nurse', $group['options']);

        // And `/jobs` reads the same declaration for its whitelist, so the
        // parameter works the moment it is served.
        JobPosting::factory()->create(['role' => 'Nurse']);
        JobPosting::factory()->create(['role' => 'Doctor']);

        $this->getJson("{$this->api}/jobs?role[]=Nurse")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * The first write copies the shipped set in. Without it, adding one
     * filter would leave that filter as the only one — any row at all counts
     * as an override — and silently delete the other five.
     */
    public function test_adding_the_first_filter_keeps_the_shipped_ones(): void
    {
        $this->actingAsAdmin();

        $groups = $this->postJson("{$this->api}/admin/job-filters", $this->roleFilter())
            ->assertCreated()
            ->json('data');

        $this->assertSame(
            ['experience', 'salary', 'job_type', 'shift', 'city', 'role'],
            array_column($groups, 'key'),
        );
    }

    /**
     * The whitelist is the point of the feature, not decoration: whoever can
     * edit reference data must not be able to point a filter at any column on
     * `job_postings` and read it back through the public `GET /jobs`.
     */
    public function test_a_filter_cannot_be_pointed_at_an_arbitrary_column(): void
    {
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/job-filters", [
            ...$this->roleFilter(),
            'key' => 'snoop',
            'param' => 'snoop',
            'column' => 'posting_status',
        ])->assertStatus(422)
            ->assertJsonPath('errors.column.0', 'That column cannot be filtered on.');
    }

    public function test_a_filter_must_draw_its_chips_from_a_real_list(): void
    {
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/job-filters", [
            ...$this->roleFilter(),
            'key' => 'made_up',
            'param' => 'made_up',
            'list' => 'not_a_list',
        ])->assertStatus(422);
    }

    public function test_two_filters_cannot_share_a_key(): void
    {
        $this->actingAsAdmin();

        $this->postJson("{$this->api}/admin/job-filters", [
            ...$this->roleFilter(),
            'key' => 'city',
        ])->assertStatus(422);
    }

    public function test_a_filter_can_be_renamed_removed_and_reset(): void
    {
        $this->actingAsAdmin();

        $listed = $this->getJson("{$this->api}/admin/job-filters")->json('data.groups');
        $this->assertNull($listed[0]['id'], 'shipped filters have no row yet');

        // The first write materialises the set, so everything comes back with
        // ids an operator can edit.
        $created = $this->postJson("{$this->api}/admin/job-filters", $this->roleFilter())
            ->assertCreated()
            ->json('data');

        $city = collect($created)->firstWhere('key', 'city');

        $this->patchJson("{$this->api}/admin/job-filters/{$city['id']}", [
            'key' => 'city',
            'label' => 'Location',
            'param' => 'city',
            'list' => 'cities',
            'column' => 'city',
            'type' => 'in',
        ])->assertOk();

        $this->assertSame(
            'Location',
            collect($this->getJson("{$this->api}/config/options")->json('data.job_filters'))
                ->firstWhere('key', 'city')['label'],
        );

        $this->deleteJson("{$this->api}/admin/job-filters/{$city['id']}")->assertOk();

        $this->assertNotContains('city', $this->listedKeys());

        // And back to the shipped five, in the shipped order.
        $this->deleteJson("{$this->api}/admin/job-filters/override")->assertOk();

        $this->assertSame(
            ['experience', 'salary', 'job_type', 'shift', 'city'],
            $this->listedKeys(),
        );
    }

    public function test_a_read_only_operator_cannot_change_the_filters(): void
    {
        $this->actingAsAdmin('viewer');

        $this->getJson("{$this->api}/admin/job-filters")->assertOk();

        $this->postJson("{$this->api}/admin/job-filters", $this->roleFilter())
            ->assertForbidden();
    }

    /** @return array<string, string> */
    private function roleFilter(): array
    {
        return [
            'key' => 'role',
            'label' => 'Role',
            'param' => 'role',
            'list' => 'categories',
            'column' => 'role',
            'type' => 'in',
        ];
    }

    /** @return list<string> */
    private function listedKeys(): array
    {
        return array_column(
            $this->getJson("{$this->api}/admin/job-filters")->json('data.groups'),
            'key',
        );
    }
}
