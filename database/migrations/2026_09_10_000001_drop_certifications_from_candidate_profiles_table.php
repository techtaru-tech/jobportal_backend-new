<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Certifications section is gone from the app — no screen writes it, no
 * response returns it, and no job requirement reads it — so the columns go
 * too rather than sitting there collecting stale rows.
 *
 * Both halves are guarded with `hasColumn`: the create migration no longer
 * makes these columns, so on a fresh database there is nothing to drop, and
 * a rollback on a fresh database must not add them back twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            foreach (['certifications', 'certification_years'] as $column) {
                if (Schema::hasColumn('candidate_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('candidate_profiles', 'certifications')) {
                $table->json('certifications')->nullable();
            }

            if (! Schema::hasColumn('candidate_profiles', 'certification_years')) {
                $table->json('certification_years')->nullable();
            }
        });
    }
};
