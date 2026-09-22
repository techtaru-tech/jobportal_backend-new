<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the last specialization column.
 *
 * `2026_09_21_000002` took the profile-level list and deliberately left this
 * one alone, on the reasoning that a degree is in a subject and that belongs
 * to the degree. That reasoning has been overruled: specialization is out of
 * the product entirely. The education form does not ask for it, Create profile
 * does not ask for it, neither resume renderer prints it, and the resource no
 * longer sends it — so the column is one nothing can fill and nothing reads.
 *
 * The `specializations` option list goes with it (config/options.php and
 * `OptionListService::EDITABLE_LISTS`): a curated list an admin can edit but
 * no picker in the app can show is worse than no list at all.
 *
 * **This drops data and `down()` cannot bring it back.** Take a dump first if
 * the specialisations on record still matter:
 *
 *     mysqldump -u <user> -p <database> > pre-drop-education-specialization.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('educations', function (Blueprint $table) {
            $table->dropColumn('specialization');
        });
    }

    public function down(): void
    {
        Schema::table('educations', function (Blueprint $table) {
            $table->string('specialization')->nullable();
        });
    }
};
