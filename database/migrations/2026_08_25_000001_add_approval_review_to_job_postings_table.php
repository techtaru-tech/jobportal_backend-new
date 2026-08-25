<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Puts every new posting behind an admin review step.
 *
 * Before this, `posting_status` defaulted to `active`: a recruiter's job was
 * live the moment the request returned, and the only gate was whether their
 * organisation happened to be verified — so the second posting under an
 * already-verified employer was never looked at by anyone. The employer flow
 * ends in "Pending for Approval", and this is the column state that makes
 * that real.
 *
 * The three review columns are nullable and stay null for the whole of a
 * posting's life unless an admin acts on it, so nothing has to be
 * backfilled — see the `existing rows` note in `up()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            // Who decided, and when. `reviewed_by_admin_id` is a plain
            // unsigned int rather than a constrained FK: admins are a
            // separate guard with their own table, and deleting a
            // long-departed admin must not cascade into rewriting the
            // history of jobs they once approved.
            $table->timestamp('reviewed_at')->nullable()->after('posting_status');
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable()->after('reviewed_at');

            // Shown to the recruiter on a rejected posting, so "rejected" is
            // never a dead end they cannot act on.
            $table->string('rejection_reason', 500)->nullable()->after('reviewed_by_admin_id');

            // The approval queue is read as "oldest waiting first", which is
            // this index exactly.
            $table->index(['posting_status', 'created_at'], 'job_postings_review_queue_index');
        });

        // Existing rows keep whatever status they already carry. A live job
        // that candidates can already see must not silently vanish into a
        // review queue because the rules changed underneath it — only
        // postings created from here on are held for approval.
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('posting_status', 20)->default('pending_approval')->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('posting_status', 20)->default('active')->change();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex('job_postings_review_queue_index');
            $table->dropColumn(['reviewed_at', 'reviewed_by_admin_id', 'rejection_reason']);
        });
    }
};
