<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin panel operators — deliberately a separate table from `users`,
     * not a flag or a role on it.
     *
     * Two reasons this is not `users.role = 'admin'`:
     *
     *  1. `UserRole` is validated with `Rule::in(UserRole::values())` on the
     *     PUBLIC, unauthenticated OTP endpoints. Adding an `admin` case would
     *     let anyone mint themselves an admin account by posting
     *     `role=admin` to `/auth/otp/send`. That is a privilege-escalation
     *     hole, not a refactor.
     *  2. App accounts authenticate by phone OTP with a nullable password.
     *     An admin panel wants email + password (and, later, MFA), which is a
     *     different credential model with a different threat profile.
     *
     * Keeping them apart means the app's auth flow is untouched by this
     * feature, and no app user can ever become an admin by accident.
     */
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            // Coarse capability tiers. Kept as a plain string rather than a
            // permissions matrix: there are two meaningful answers today —
            // "can change things" and "can only look" — and a full RBAC
            // table would be scaffolding with nothing to hold up yet.
            $table->string('role', 20)->default('admin'); // admin | viewer

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
