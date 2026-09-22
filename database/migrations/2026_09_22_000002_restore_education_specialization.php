<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings `educations.specialization` back.
 *
 * `2026_09_22_000001` dropped it on the reading that specialization was out of
 * the product. That was wrong for the education entry: a degree is in a
 * subject, and a B.Sc Nursing in Critical Care is a different candidate from a
 * B.Sc Nursing in Paediatrics. The *profile-level* list stays gone — that was
 * the Smart Apply field, and nothing asks for it.
 *
 * Whatever was in the column before the drop is not coming back; that data was
 * lost when it ran. Existing rows come back null and the form collects it again
 * from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('educations', function (Blueprint $table) {
            $table->string('specialization')->nullable()->after('qualification');
        });
    }

    public function down(): void
    {
        Schema::table('educations', function (Blueprint $table) {
            $table->dropColumn('specialization');
        });
    }
};
