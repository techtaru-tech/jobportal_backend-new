<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One active plan per account per side of the marketplace.
 *
 * Subscriptions used to live only in the app's local storage, which made them
 * a property of the device rather than the account: reinstalling dropped a
 * paid plan, a second device never saw it, and the recruiter posting limit
 * was enforced by the same client it was meant to restrain.
 *
 * `audience` rather than `role` — one account holds both sides, and the two
 * plans are independent (a free job seeker can be a paying recruiter).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 20);
            $table->string('plan_id', 40);
            $table->timestamp('started_at')->nullable();

            // Null means "never expires" — every free plan, and what a paid
            // plan is read as once its period has run out.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // The row *is* the current plan for that side; subscribing again
            // updates it rather than stacking history.
            $table->unique(['user_id', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
