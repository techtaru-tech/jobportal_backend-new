<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "What are your hobbies?" — the client's own example of an optional filler
 * question. Kept on the same `PATCH /candidate/profile/about` endpoint as
 * `about` rather than given one of its own: both are short, optional, free
 * text about the person rather than their qualifications, and a candidate
 * fills them in on the same screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->string('hobbies', 500)->nullable()->after('about');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('hobbies');
        });
    }
};
