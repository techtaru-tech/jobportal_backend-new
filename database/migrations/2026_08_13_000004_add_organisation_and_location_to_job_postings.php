<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §8.1 — a job is always posted *for* an employer, not against a free-text
     * name. `organisation` stays as a denormalised copy of the name so the
     * candidate-facing card renders without a join, alongside the live
     * `organisation_verified` flag.
     *
     * The column is nullable so pre-existing postings survive the migration;
     * the API requires it on every new posting.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();

            $table->string('pincode', 10)->nullable()->after('city');
            $table->decimal('latitude', 10, 7)->nullable()->after('pincode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropColumn(['pincode', 'latitude', 'longitude']);
        });
    }
};
