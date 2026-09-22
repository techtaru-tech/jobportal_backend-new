<?php

namespace App\Console\Commands;

use App\Models\JobPosting;
use Illuminate\Console\Command;

/**
 * Closes postings whose expiry date has passed.
 *
 * `JobPosting::expireOverdue()` existed and had no caller: nothing in a
 * controller, nothing on a schedule. So `expires_at` was a date the recruiter
 * and the admin could both set (`PATCH /admin/jobs/{id}/expiry`) and neither
 * could make happen — the posting stayed `active`, stayed on the board, and
 * went on taking applications for a vacancy that had closed.
 *
 * A command rather than a lazy check inside the browse query, because the
 * status is real state that four different screens read (the recruiter's My
 * jobs, the admin's list and its filters, the applicant gate). Deriving it in
 * one of them would leave the other three disagreeing.
 */
class ExpireOverduePostings extends Command
{
    protected $signature = 'postings:expire-overdue';

    protected $description = 'Mark active and paused postings past their expiry date as expired';

    public function handle(): int
    {
        $count = JobPosting::expireOverdue();

        $this->info($count === 0
            ? 'Nothing was overdue.'
            : "Expired {$count} posting".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
