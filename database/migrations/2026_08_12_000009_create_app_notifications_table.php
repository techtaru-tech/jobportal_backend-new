<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §10 Notifications. Named `app_notifications` to leave the conventional
     * `notifications` table free for Laravel's own notification system if a
     * later version wires up FCM/APNs delivery.
     */
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('text');
            $table->string('type', 40)->default('system');
            $table->timestamp('read_at')->nullable();

            // Deep-link targets — whichever is relevant for `type`.
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
