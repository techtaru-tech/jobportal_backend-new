<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every state-changing admin action, recorded.
     *
     * Not optional bookkeeping: admin writes in this product are visible to
     * end users and cannot be quietly undone. Verifying an organisation flips
     * a public trust badge. Changing an application's status writes the
     * candidate's own timeline AND fires them a push notification. Editing an
     * option list changes what every picker in every installed app offers.
     *
     * When one of those turns out to have been wrong, the only question worth
     * asking is "who did this, when, and what was it before" — which is
     * exactly what this table answers and nothing else in the schema can.
     */
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();

            // Nullable so deleting an operator never deletes the record of
            // what they did; `admin_email` keeps it readable afterwards.
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_email');

            $table->string('action', 60);          // 'organisation.verify', 'job.status', …
            $table->string('subject_type', 40)->nullable(); // 'JobPosting', 'Organisation', …
            $table->string('subject_id', 60)->nullable();   // public id or reference
            $table->string('summary');             // one human-readable line

            // before/after for the fields that actually changed.
            $table->json('changes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
