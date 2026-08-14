<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §3.1 additions: where the candidate lives (distinct from where they want
     * to work), per-skill proficiency, and the optional intro video.
     */
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            // Home location — where they live. `location` remains the list of
            // cities they want to work in. Coordinates drive distance-based
            // recommendation; null just falls back to a city-name lookup.
            $table->string('home_city', 80)->nullable()->after('address');
            $table->string('home_pincode', 10)->nullable()->after('home_city');
            $table->decimal('home_latitude', 10, 7)->nullable()->after('home_pincode');
            $table->decimal('home_longitude', 10, 7)->nullable()->after('home_latitude');

            // skill -> level, additive to `skills`; a skill with no entry simply
            // has no level on record.
            $table->json('skill_levels')->nullable()->after('skills');

            // §3.13 — deliberately NOT part of profile_strength.
            $table->string('intro_video_path')->nullable()->after('resume_path');
            $table->string('intro_video_thumbnail_path')->nullable()->after('intro_video_path');
            $table->unsignedSmallInteger('intro_video_seconds')->nullable()->after('intro_video_thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'home_city', 'home_pincode', 'home_latitude', 'home_longitude',
                'skill_levels', 'intro_video_path', 'intro_video_thumbnail_path',
                'intro_video_seconds',
            ]);
        });
    }
};
