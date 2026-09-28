<?php

namespace Tests\Unit;

use App\Models\WorkExperience;
use App\Support\ExperienceSpan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/** Duration and overlap-aware total-experience math behind `WorkExperience::duration()` and `CandidateProfile::computedExperienceYears()`. */
class ExperienceSpanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 6, 15));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function role(string $start, ?string $end, bool $current = false): WorkExperience
    {
        return new WorkExperience([
            'start_date' => $start,
            'end_date' => $end,
            'currently_working' => $current,
        ]);
    }

    public function test_a_role_worked_across_two_calendar_years_is_measured_inclusively(): void
    {
        $this->assertSame('1 yr 9 mos', ExperienceSpan::durationLabel($this->role('Jan 2022', 'Sep 2023')));
    }

    public function test_a_role_started_and_ended_the_same_month_is_one_month_not_zero(): void
    {
        $this->assertSame('1 mo', ExperienceSpan::durationLabel($this->role('Mar 2023', 'Mar 2023')));
    }

    public function test_a_current_role_is_measured_up_to_now(): void
    {
        // Test clock is 15 Jun 2026 — Jan 2026 to now is 6 inclusive months.
        $this->assertSame('6 mos', ExperienceSpan::durationLabel($this->role('Jan 2026', 'Present', current: true)));
    }

    public function test_an_unparseable_date_has_no_duration(): void
    {
        $this->assertNull(ExperienceSpan::durationLabel($this->role('a while back', null)));
    }

    public function test_total_experience_sums_non_overlapping_roles(): void
    {
        $roles = new Collection([
            $this->role('Jan 2018', 'Dec 2019'),
            $this->role('Jan 2020', 'Dec 2020'),
        ]);

        // 24 months + 12 months.
        $this->assertSame(3.0, ExperienceSpan::totalYears($roles));
    }

    public function test_total_experience_merges_overlapping_roles(): void
    {
        $roles = new Collection([
            $this->role('Jan 2020', 'Dec 2020'),
            $this->role('Jul 2020', 'Jun 2021'),
        ]);

        // Jan 2020 – Jun 2021 merged: 18 months, not 12 + 12 = 24.
        $this->assertSame(1.5, ExperienceSpan::totalYears($roles));
    }

    public function test_total_experience_ignores_entries_it_cannot_parse(): void
    {
        $roles = new Collection([
            $this->role('Jan 2020', 'Dec 2020'),
            $this->role('sometime, ages ago', null),
        ]);

        $this->assertSame(1.0, ExperienceSpan::totalYears($roles));
    }

    public function test_total_experience_is_null_when_nothing_parses(): void
    {
        $roles = new Collection([$this->role('sometime, ages ago', null)]);

        $this->assertNull(ExperienceSpan::totalYears($roles));
    }
}
