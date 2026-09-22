<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The salary filter against postings that quote a range.
 *
 * `min_salary` compared the candidate's floor to the posting's floor, so a
 * posting's entire upper range was invisible to it. On the live board that
 * meant "₹75K+" returned nothing while a ₹70K–₹1L posting sat there, and
 * "₹30K+" dropped a ₹28K–₹42K one.
 *
 * The question a threshold asks is whether the posting can pay it, which is a
 * question about its ceiling.
 */
class JobSalaryFilterTest extends TestCase
{
    use RefreshDatabase;

    private function posting(?int $min, ?int $max, string $title): void
    {
        JobPosting::factory()->create([
            'title' => $title,
            'salary_min' => $min,
            'salary_max' => $max,
        ]);
    }

    /**
     * Sorted, because the listing orders by `posted_at` and the factory
     * stamps every posting with the same instant — the order between them is
     * the database's business, and not what any of this is about.
     *
     * @return list<string>
     */
    private function titlesAtLeast(int $threshold): array
    {
        $titles = $this->getJson("{$this->api}/jobs?min_salary={$threshold}")
            ->assertOk()
            ->json('data.*.title');

        sort($titles);

        return $titles;
    }

    public function test_a_range_that_reaches_the_threshold_is_shown(): void
    {
        // The reported case: the job tops out well above what was asked, and
        // was hidden because it starts below it.
        $this->posting(16000, 26000, 'Lab Technician');

        $this->assertSame(['Lab Technician'], $this->titlesAtLeast(20000));
    }

    public function test_a_range_that_stops_short_of_it_is_not(): void
    {
        $this->posting(16000, 26000, 'Lab Technician');

        $this->assertSame([], $this->titlesAtLeast(30000));
    }

    public function test_a_ceiling_exactly_on_the_threshold_counts(): void
    {
        // "₹50K+" means at least ₹50K, so a job topping out at exactly ₹50K
        // qualifies. An off-by-one here silently drops a whole salary step.
        $this->posting(35000, 50000, 'Cath Lab Nurse');

        $this->assertSame(['Cath Lab Nurse'], $this->titlesAtLeast(50000));
        $this->assertSame([], $this->titlesAtLeast(50001));
    }

    public function test_a_posting_quoting_only_a_floor_is_judged_on_that(): void
    {
        // Both salary columns are nullable and the recruiter form does not
        // force an upper bound, so "₹25K" with no ceiling is a real posting.
        // Its floor is the only figure it has.
        $this->posting(25000, null, 'Flat Rate Nurse');

        $this->assertSame(['Flat Rate Nurse'], $this->titlesAtLeast(20000));
        $this->assertSame(['Flat Rate Nurse'], $this->titlesAtLeast(25000));
        $this->assertSame([], $this->titlesAtLeast(30000));
    }

    public function test_a_posting_quoting_no_salary_matches_no_threshold(): void
    {
        // Nothing to promise the threshold is met. It still browses fine
        // unfiltered — it is only absent once a figure is asked for.
        $this->posting(null, null, 'Salary Not Disclosed');

        $this->assertSame([], $this->titlesAtLeast(10000));
        $this->assertSame(
            ['Salary Not Disclosed'],
            $this->getJson("{$this->api}/jobs")->json('data.*.title'),
        );
    }

    public function test_the_threshold_sorts_the_board_the_way_a_candidate_expects(): void
    {
        // The shape of the bug, end to end: raising the threshold should drop
        // postings one at a time from the bottom, never skip one that pays.
        $this->posting(16000, 26000, 'A');
        $this->posting(28000, 42000, 'B');
        $this->posting(70000, 100000, 'C');

        $this->assertSame(['A', 'B', 'C'], $this->titlesAtLeast(20000));
        $this->assertSame(['B', 'C'], $this->titlesAtLeast(30000));
        $this->assertSame(['C'], $this->titlesAtLeast(50000));
        // The one that used to return nothing at all.
        $this->assertSame(['C'], $this->titlesAtLeast(75000));
    }
}
