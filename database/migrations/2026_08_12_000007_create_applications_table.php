<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §6 Applications.
     *
     * `reference` is the human-friendly id: the job code plus a per-application
     * disambiguator ("MC-10245-A1B2"), exactly the shape §6.1 recommends. The
     * candidate view renders it with a leading "#", the recruiter view without.
     *
     * `profile_snapshot` is an immutable blob — editing a profile later must
     * never change what an organisation already received (§6.1).
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('submitted');
            $table->timestamp('applied_at');
            $table->json('profile_snapshot');
            $table->timestamps();

            $table->unique(['job_posting_id', 'user_id']);
            $table->index(['job_posting_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('application_timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 30);
            $table->timestamp('at');
            $table->timestamps();

            $table->index(['application_id', 'at']);
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('date');
            // Display string ("11:00 AM") — matches InterviewDetails in the app.
            $table->string('time', 20);
            $table->string('type', 20);
            $table->string('location_or_link')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('application_timeline_entries');
        Schema::dropIfExists('applications');
    }
};
