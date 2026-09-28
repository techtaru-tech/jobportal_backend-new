<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of organisation a candidate wants to work at — Hospital,
 * Diagnostic Lab, Pharmacy, and so on.
 *
 * Deliberately no new vocabulary: this reuses `organisation_industries`, the
 * same list an employer picks their own industry from when they add a
 * company. A candidate's stated preference and a job's actual
 * `organisation_industry` have to speak the same words to ever be matched
 * against each other — two separate lists here would drift the moment either
 * one was edited from the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->json('preferred_organisation_types')->nullable()->after('preferred_shifts');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('preferred_organisation_types');
        });
    }
};
