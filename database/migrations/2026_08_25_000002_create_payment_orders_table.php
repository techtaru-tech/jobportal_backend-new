<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One attempt to pay for a plan.
 *
 * Until now `POST /subscription` activated a paid plan on the spot and the
 * money was imaginary — there was no record that a charge was ever meant to
 * happen, so nothing could be reconciled, refunded or even counted. A plan now
 * activates only when the order that bought it is captured.
 *
 * Rows are kept whatever the outcome: an abandoned or failed attempt is the
 * useful half of a funnel, and deleting it would leave only the successes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Which side of the marketplace bought it — the same split
            // `subscriptions` uses, since one account can pay on both.
            $table->string('audience', 20);
            $table->string('plan_id', 40);

            // Paise, matching `price_paise` in config/plans.php. Copied onto
            // the order rather than read back from config at capture time, so
            // a later price change never rewrites what somebody was charged.
            $table->unsignedInteger('amount_paise');
            $table->string('currency', 3)->default('INR');

            // 'upi' | 'card' | 'netbanking' — what the payer chose.
            $table->string('method', 20)->nullable();

            // 'created' | 'paid' | 'failed'
            $table->string('status', 20)->default('created');

            // Which driver handled it, and its own id for the transaction.
            // Null on a test-gateway order that never left this server.
            $table->string('gateway', 20)->default('test');
            $table->string('gateway_ref', 120)->nullable();
            $table->string('failure_reason', 255)->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // "This user's orders, newest first" — the only way it is listed.
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
