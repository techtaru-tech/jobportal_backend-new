<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a chat screen tell the server "I'm looking at this thread right
     * now" — the same shape as the existing typing flag — so a new message
     * can skip the push notification for whichever side is already watching
     * it arrive live, the way WhatsApp does.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('recruiter_viewing')->default(false);
            $table->boolean('candidate_viewing')->default(false);
            $table->timestamp('recruiter_viewing_at')->nullable();
            $table->timestamp('candidate_viewing_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['recruiter_viewing', 'candidate_viewing', 'recruiter_viewing_at', 'candidate_viewing_at']);
        });
    }
};
