<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §4.1 JobModel. Named `job_postings` because `jobs` is taken by Laravel's
     * queue table; the API still calls these "jobs" everywhere.
     */
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();

            $table->string('role');
            $table->string('title');
            $table->string('organisation');
            $table->string('organisation_note')->nullable();
            $table->string('city');

            // Real structured values; the display strings are derived (§1.7).
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->string('experience', 40)->nullable();
            $table->unsignedTinyInteger('experience_min_years')->nullable();
            $table->unsignedTinyInteger('experience_max_years')->nullable();

            $table->string('type', 30);
            $table->string('shift', 30);

            $table->timestamp('posted_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('posting_status', 20)->default('active');

            $table->json('required_fields')->nullable();
            $table->text('about')->nullable();
            $table->json('duties')->nullable();
            $table->json('qualifications')->nullable();
            $table->json('skills')->nullable();
            $table->json('benefits')->nullable();

            $table->timestamps();

            $table->index(['posting_status', 'posted_at']);
            $table->index('role');
            $table->index('city');
            $table->index('type');
            $table->index('shift');
            $table->index('salary_min');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
