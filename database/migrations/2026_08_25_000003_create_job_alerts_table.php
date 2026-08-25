<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A standing search a candidate wants to hear about.
 *
 * The employee flow ends in "Job Alerts", and nothing implemented it: the
 * `job_match` notification type existed and was only ever fired at the
 * recruiter who posted a job. This is the table that lets a candidate say
 * *which* new postings are worth waking them for.
 *
 * Every criterion is nullable, and a null one matches everything — an alert
 * with all three blank is "tell me about every new job", which is a legitimate
 * thing to want and needs no special case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Matched against the posting's own columns. Stored as the
            // display strings the app already uses for its pickers, so an
            // alert reads back as the same words the candidate chose.
            $table->string('role', 80)->nullable();
            $table->string('city', 80)->nullable();

            // Free text, matched against title and skills — the part a
            // picker cannot cover.
            $table->string('keyword', 120)->nullable();

            // Paused rather than deleted: the criteria are the work, and
            // somebody who is mid-notice-period wants them back next month.
            $table->boolean('is_active')->default(true);

            // When this alert last caused a notification. Not a match count —
            // "am I actually hearing anything from this" is the question, and
            // a null here is the answer that matters.
            $table->timestamp('last_notified_at')->nullable();

            $table->timestamps();

            // The two ways it is read: a candidate's own list, and "every
            // active alert" when a posting goes live.
            $table->index(['user_id', 'created_at']);
            $table->index(['is_active', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_alerts');
    }
};
