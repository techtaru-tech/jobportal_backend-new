<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            // Null throughout means "not answered yet", not "no" — a blank
            // availability section must not read as "not currently employed".
            $table->boolean('currently_employed')->nullable()->after('expected_salary');
            $table->string('notice_period')->nullable()->after('currently_employed');
            $table->date('last_working_date')->nullable()->after('notice_period');
            $table->boolean('immediate_joiner')->nullable()->after('last_working_date');
            $table->date('earliest_joining_date')->nullable()->after('immediate_joiner');
            $table->boolean('willing_to_relocate')->nullable()->after('earliest_joining_date');
            $table->boolean('has_vehicle')->nullable()->after('willing_to_relocate');
            $table->boolean('has_driving_licence')->nullable()->after('has_vehicle');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'currently_employed', 'notice_period', 'last_working_date',
                'immediate_joiner', 'earliest_joining_date',
                'willing_to_relocate', 'has_vehicle', 'has_driving_licence',
            ]);
        });
    }
};
