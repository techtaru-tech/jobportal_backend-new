<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->string('marital_status')->nullable()->after('gender');
            $table->string('father_name')->nullable()->after('marital_status');
            $table->string('mother_name')->nullable()->after('father_name');
            $table->string('home_state')->nullable()->after('home_city');
            $table->string('native_place')->nullable()->after('home_state');
            $table->string('nationality')->nullable()->after('native_place');
            $table->string('alternate_phone', 15)->nullable()->after('nationality');
            $table->string('whatsapp_number', 15)->nullable()->after('alternate_phone');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'marital_status', 'father_name', 'mother_name',
                'home_state', 'native_place', 'nationality',
                'alternate_phone', 'whatsapp_number',
            ]);
        });
    }
};
