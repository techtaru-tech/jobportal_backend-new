<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per installed app instance that has push permission.
 *
 * Keyed on the FCM token, not on (user, platform): the same physical device
 * can only ever hold one live token per app install, and a fresh token FCM
 * hands out (app reinstalled, storage cleared) needs to replace the old row
 * rather than sit next to it as a second one nothing will ever push to
 * again.
 *
 * Deliberately per-user, not per-account-and-audience: one account holds both
 * sides of the marketplace (see the 2026_08_17 migration), and a single
 * signed-in device should get pushed for both — a recruiter reply and an
 * application-status update both belong on the same phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('platform', 10);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
