<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** §3.1 — the CandidateProfile the app reads on every screen. */
    public function up(): void
    {
        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Basic / personal (§3.2)
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('dob')->nullable();
            $table->string('address')->nullable();

            // Smart Apply fields (§3.3)
            $table->string('qualification')->nullable();
            $table->string('experience', 40)->nullable();
            $table->unsignedTinyInteger('experience_min_years')->nullable();
            $table->unsignedTinyInteger('experience_max_years')->nullable();
            $table->json('skills')->nullable();
            $table->json('location')->nullable();
            $table->json('specialization')->nullable();

            // Preferences (§3.8)
            $table->json('preferred_roles')->nullable();
            $table->json('preferred_job_types')->nullable();
            $table->json('preferred_shifts')->nullable();
            $table->string('expected_salary', 40)->nullable();
            $table->unsignedInteger('expected_salary_amount')->nullable();

            // Certifications + languages (§3.6, §3.7)
            $table->json('certifications')->nullable();
            $table->json('certification_years')->nullable();
            $table->json('languages')->nullable();
            $table->json('language_levels')->nullable();

            // About / media (§3.9, §3.10, §3.11)
            $table->text('about')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('resume_name')->nullable();
            $table->string('resume_path')->nullable();

            // Denormalised so recruiters can sort applicants by it in SQL (§8.1).
            $table->unsignedTinyInteger('profile_strength')->default(0);

            $table->timestamps();

            $table->index('qualification');
            $table->index('experience');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_profiles');
    }
};
