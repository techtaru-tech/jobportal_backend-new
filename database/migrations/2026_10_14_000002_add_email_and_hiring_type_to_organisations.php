<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Post a Job ▸ step 3 asks for the company's email and who it hires for. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('email', 190)->nullable()->after('name');
            $table->string('hiring_type', 20)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['email', 'hiring_type']);
        });
    }
};
