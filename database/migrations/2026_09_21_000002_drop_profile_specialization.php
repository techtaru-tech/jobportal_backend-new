<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the profile-level specialization list.
 *
 * The section is gone from both sides of the app — no step collects it, no
 * screen shows it, no endpoint accepts it — so what is left is a JSON column
 * nothing reads.
 *
 * Education entries keep their own `specialization`, and that one is
 * untouched: a degree is in a subject, and that has always been a property of
 * the degree rather than of the person.
 *
 * **This drops data and `down()` cannot bring it back.** Take a dump first if
 * the specialisations on record still matter:
 *
 *     mysqldump -u <user> -p <database> > pre-drop-specialization.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('specialization');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->json('specialization')->nullable();
        });
    }
};
