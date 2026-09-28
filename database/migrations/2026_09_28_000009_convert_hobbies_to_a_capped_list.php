<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `hobbies` shipped hours ago as a free-text line. The client's fuller spec
 * (received the same day) asks for 2–3 selected interests instead — a
 * closed, resume-friendly list rather than whatever a candidate typed. Any
 * free text already saved is carried forward as a single-item list rather
 * than dropped, even though it will read oddly until the candidate reopens
 * the picker and reselects from the real list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->json('hobbies_list')->nullable()->after('hobbies');
        });

        DB::table('candidate_profiles')
            ->whereNotNull('hobbies')
            ->where('hobbies', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('candidate_profiles')
                    ->where('id', $row->id)
                    ->update(['hobbies_list' => json_encode([$row->hobbies])]);
            });

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('hobbies');
        });

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->renameColumn('hobbies_list', 'hobbies');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->string('hobbies_string', 500)->nullable()->after('hobbies');
        });

        DB::table('candidate_profiles')
            ->whereNotNull('hobbies')
            ->orderBy('id')
            ->each(function ($row) {
                $list = json_decode($row->hobbies, true) ?? [];
                DB::table('candidate_profiles')
                    ->where('id', $row->id)
                    ->update(['hobbies_string' => implode(', ', $list)]);
            });

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('hobbies');
        });

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->renameColumn('hobbies_string', 'hobbies');
        });
    }
};
