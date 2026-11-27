<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The payment gateway's own order id (Razorpay `order_…`), created when the
 * checkout opens — separate from `gateway_ref`, which is the settled payment
 * (`pay_…`) and is only known once the money has moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->string('gateway_order_id', 60)->nullable()->after('gateway')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropIndex(['gateway_order_id']);
            $table->dropColumn('gateway_order_id');
        });
    }
};
