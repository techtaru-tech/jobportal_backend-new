<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auth is OTP-only (§2) — no passwords, and a phone identifies the account.
     * A phone may hold one candidate account and one recruiter account; there is
     * no dual-role merging, so (phone, role) is the natural key.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->after('id');
            $table->string('role', 20)->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('role');
            $table->timestamp('last_login_at')->nullable()->after('phone_verified_at');

            $table->unique(['phone', 'role']);
            $table->index('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone', 'role']);
            $table->dropIndex(['role']);
            $table->dropColumn(['phone', 'role', 'phone_verified_at', 'last_login_at']);
        });
    }
};
