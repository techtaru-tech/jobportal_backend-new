<?php

use App\Models\WorkExperience;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A role can be worked across more than one location — a staffing agency
 * placement rotating between two hospitals in the same city group is the
 * ordinary case, not an edge one — so `city` (one string) becomes `cities`
 * (a JSON list), the same shape `candidate_profiles.location` already uses
 * for the candidate's own preferred cities.
 *
 * Existing rows are carried forward rather than dropped: a filled `city`
 * becomes a one-element `cities` list, and a blank one becomes an empty list
 * — nobody's work history goes blank because of this migration.
 *
 * **This drops the `city` column and `down()` cannot restore more than the
 * first city of a multi-city entry.** Take a dump first if the exact per-row
 * text still matters:
 *
 *     mysqldump -u <user> -p <database> > pre-cities-migration.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_experiences', function (Blueprint $table) {
            $table->json('cities')->nullable()->after('city');
        });

        WorkExperience::withoutGlobalScopes()->get()->each(function (WorkExperience $experience) {
            $experience->forceFill([
                'cities' => filled($experience->city) ? [$experience->city] : [],
            ])->saveQuietly();
        });

        Schema::table('work_experiences', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }

    public function down(): void
    {
        Schema::table('work_experiences', function (Blueprint $table) {
            $table->string('city', 80)->nullable()->after('department');
        });

        WorkExperience::withoutGlobalScopes()->get()->each(function (WorkExperience $experience) {
            $experience->forceFill([
                'city' => $experience->cities[0] ?? null,
            ])->saveQuietly();
        });

        Schema::table('work_experiences', function (Blueprint $table) {
            $table->dropColumn('cities');
        });
    }
};
