<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the skills columns. The section is gone from the product — no
 * endpoint writes them, no resource exposes them, no requirement gates on
 * them — so what is left is four JSON columns nothing reads.
 *
 * `applications.snapshot_skills` goes with the rest. It is the frozen copy of
 * a candidate's skills at submission time, and a snapshot of a field that no
 * longer exists cannot be shown next to the live profile it is meant to be
 * compared against.
 *
 * **This drops data and `down()` cannot bring it back** — the columns return
 * empty. Take a dump first if the skills on record still matter to anyone:
 *
 *     mysqldump -u <user> -p <database> > pre-drop-skills.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn(['skills', 'skill_levels']);
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('skills');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('snapshot_skills');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->json('skills')->nullable();
            $table->json('skill_levels')->nullable();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->json('skills')->nullable();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->json('snapshot_skills')->nullable();
        });
    }
};
