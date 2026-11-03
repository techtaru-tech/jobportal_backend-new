<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approve without the business document.
 *
 * An admin may publish a job before its company's document is in. The job is
 * live for `job_plans.document_grace_days`; the employer is reminded to
 * upload it, and if nothing arrives by `document_due_at` the job is hidden
 * from candidates until it does (JobPosting::scopePubliclyVisible).
 *
 * The reminder counters move to the company: `postings:remind-missing-
 * documents` now records what it sent, so running it twice in a day sends
 * nothing twice and a day the server missed is caught up the next morning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            // Set only when approved without the document; null = not waived.
            $table->timestamp('document_due_at')->nullable()->after('expires_at');
            // The one "hidden — the document never came" message per job.
            $table->timestamp('document_overdue_notified_at')->nullable()->after('document_due_at');
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->unsignedTinyInteger('document_reminders_sent')->default(0)->after('document_path');
            $table->timestamp('document_reminded_at')->nullable()->after('document_reminders_sent');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn(['document_due_at', 'document_overdue_notified_at']);
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['document_reminders_sent', 'document_reminded_at']);
        });
    }
};
