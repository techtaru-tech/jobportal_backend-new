<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** §3.4 EducationEntry / §3.5 ExperienceEntry. */
    public function up(): void
    {
        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('qualification');
            $table->string('specialization')->nullable();
            $table->string('institute')->nullable();
            $table->string('year', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('designation');
            $table->string('organization');
            $table->string('department')->nullable();
            $table->string('city')->nullable();
            // Display strings ("Mar 2023" / "Present") per §1.7.
            $table->string('start_date', 30)->nullable();
            $table->string('end_date', 30)->nullable();
            $table->boolean('currently_working')->default(false);
            // Free-text responsibility bullets — surfaced in the recruiter's
            // ApplicantExperience view (§8.1, §12).
            $table->json('bullets')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_experiences');
        Schema::dropIfExists('educations');
    }
};
