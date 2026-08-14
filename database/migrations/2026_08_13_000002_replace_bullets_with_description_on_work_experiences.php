<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §3.5 — the spec now carries free-text `description` on a work-experience
     * entry. That supersedes the `bullets` array this build previously used to
     * fill the same gap, so existing bullets are folded into the new column.
     */
    public function up(): void
    {
        Schema::table('work_experiences', function (Blueprint $table) {
            $table->text('description')->nullable()->after('currently_working');
        });

        DB::table('work_experiences')
            ->whereNotNull('bullets')
            ->orderBy('id')
            ->each(function (object $row) {
                $bullets = json_decode($row->bullets ?? '[]', true);

                if (! is_array($bullets) || $bullets === []) {
                    return;
                }

                DB::table('work_experiences')
                    ->where('id', $row->id)
                    ->update(['description' => implode("\n", $bullets)]);
            });

        Schema::table('work_experiences', function (Blueprint $table) {
            $table->dropColumn('bullets');
        });
    }

    public function down(): void
    {
        Schema::table('work_experiences', function (Blueprint $table) {
            $table->json('bullets')->nullable()->after('currently_working');
            $table->dropColumn('description');
        });
    }
};
