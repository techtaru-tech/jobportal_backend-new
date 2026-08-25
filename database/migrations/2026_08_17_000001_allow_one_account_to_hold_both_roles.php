<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * One phone, one account, both sides of the marketplace.
 *
 * The original model gave a phone two independent user rows — one candidate,
 * one recruiter — keyed by `unique(phone, role)`. The app never worked that
 * way: `AppModeService` describes the switch as "same account, same login,
 * just a different lens" and keeps using the one token it has, so every
 * recruiter call from a candidate session came back 403.
 *
 * Two accounts is also the wrong shape for what the product does. The same
 * person hires and looks for work, and the features that span both sides —
 * chat on an application, "is this my own posting", notifications — need one
 * identity to hang off, not two that happen to share a phone number.
 *
 * `users.role` survives as the side the account signed up on. It is a default
 * for which tab opens first, not a permission: nothing gates on it after this
 * migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard rather than assume: the unique index below cannot be created
        // while a duplicate exists, and failing here with a readable message
        // beats a raw SQL error from the index build.
        $duplicates = DB::table('users')
            ->select('phone')
            ->groupBy('phone')
            ->havingRaw('count(*) > 1')
            ->pluck('phone');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot merge accounts: these phones hold both a candidate and a '
                .'recruiter row, and their profiles, jobs and applications have to '
                .'be moved onto one of them first — '.$duplicates->implode(', ')
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone', 'role']);
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->unique(['phone', 'role']);
        });
    }
};
