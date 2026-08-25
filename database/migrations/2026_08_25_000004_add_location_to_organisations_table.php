<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives an employer a real position, not just an address string.
 *
 * `organisations.address` was free text and nothing else — so an employer
 * picked on the map in the app had its coordinates thrown away on save, and
 * the recruiter's own onboarding had no structured location to collect.
 * Job postings have carried `latitude`/`longitude` since
 * `add_organisation_and_location_to_job_postings`; this brings the employer
 * behind them up to the same shape.
 *
 * All four are nullable: a typed address with no pin is still a legitimate
 * answer, and every existing row has exactly that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            // Split out of `address` for the same reason the candidate
            // profile splits them: the city is what a text search matches on,
            // and the pincode is what a courier needs.
            $table->string('city', 80)->nullable()->after('address');
            $table->string('pincode', 12)->nullable()->after('city');

            // The pair that makes distance work. Only ever written together —
            // half a coordinate pair is not a location.
            $table->decimal('latitude', 10, 7)->nullable()->after('pincode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['city', 'pincode', 'latitude', 'longitude']);
        });
    }
};
