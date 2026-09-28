<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Language -> Native / Professional / Conversational, kept beside
 * `language_levels` (what the candidate can *do* — read, write, speak) rather
 * than replacing it: the client's table asks for both, and they answer
 * different questions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->json('language_proficiencies')->nullable()->after('language_levels');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('language_proficiencies');
        });
    }
};
