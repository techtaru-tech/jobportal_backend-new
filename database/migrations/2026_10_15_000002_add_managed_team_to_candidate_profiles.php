<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Create profile ▸ Experience: "Have you managed a team?" — null until answered. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->boolean('managed_team')->nullable()->after('experience');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('managed_team');
        });
    }
};
