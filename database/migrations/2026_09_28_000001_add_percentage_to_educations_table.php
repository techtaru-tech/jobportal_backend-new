<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the marks a candidate scored in one education entry.
 *
 * A plain percentage (0–100), stored as a decimal rather than the string the
 * rest of this table uses for `year` — it is the one field here somebody
 * might reasonably want to sort or compare on, and a decimal is what makes
 * that free later without a second migration to fix the type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('educations', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)->nullable()->after('year');
        });
    }

    public function down(): void
    {
        Schema::table('educations', function (Blueprint $table) {
            $table->dropColumn('percentage');
        });
    }
};
