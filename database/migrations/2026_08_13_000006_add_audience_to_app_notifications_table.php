<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §11 — a notification is addressed to an *inbox*, not just a user. One
     * phone can hold both a candidate and a recruiter account and the app only
     * ever renders the inbox for the mode currently on screen.
     */
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->string('audience', 20)->default('jobSeeker')->after('user_id');
            $table->index(['user_id', 'audience', 'created_at'], 'app_notifications_inbox_index');
        });

        // Existing rows belong to whichever inbox their owner's role implies.
        DB::statement(<<<'SQL'
            update app_notifications
            set audience = case
                when (select role from users where users.id = app_notifications.user_id) = 'recruiter'
                    then 'recruiter'
                else 'jobSeeker'
            end
        SQL);
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndex('app_notifications_inbox_index');
            $table->dropColumn('audience');
        });
    }
};
