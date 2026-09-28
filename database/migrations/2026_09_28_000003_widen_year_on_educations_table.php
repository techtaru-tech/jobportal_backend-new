<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `year` was 10 characters, sized for a single passing year. The app now
 * collects a start year alongside it and composes both into one string —
 * `'2018 – 2022'` — which is 11. Every save of a genuine two-year range hit
 * this column's own limit and the identical one in
 * `EducationController::validated()`, and was refused with a 422 on a field
 * the candidate had answered correctly.
 *
 * 20 rather than the exact 11 needed today: the next reasonable format
 * change to this string should not need a third migration just for length.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('educations', function (Blueprint $table) {
            $table->string('year', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('educations', function (Blueprint $table) {
            $table->string('year', 10)->nullable()->change();
        });
    }
};
