<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-posting plans.
 *
 * `job_credits` — what a paid order bought: N postings at a tier and reach.
 * The posting being paid for uses one at once; the rest wait for the next
 * job, until they lapse.
 *
 * `job_plan_prices` — the price grid the plan step shows, editable from the
 * admin panel. Seeded from config/job_plans.php.
 *
 * `payment_orders.meta` — what a job-plan order is for (tier, reach,
 * postings, and the posting it pays for), since `plan_id` alone cannot say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_order_id')->nullable();
            $table->string('tier', 20);
            $table->string('reach', 20);
            $table->unsignedInteger('total');
            $table->unsignedInteger('used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('job_plan_prices', function (Blueprint $table) {
            $table->id();
            $table->string('tier', 20);
            $table->string('reach', 20);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('price_paise');
            $table->unsignedInteger('mrp_paise')->nullable();
            $table->timestamps();

            $table->unique(['tier', 'reach', 'quantity']);
        });

        Schema::table('payment_orders', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('plan_id');
        });

        $now = now();
        $rows = [];
        foreach (config('job_plans.prices', []) as $tier => $reaches) {
            foreach ($reaches as $reach => $quantities) {
                foreach ($quantities as $quantity => [$price, $mrp]) {
                    $rows[] = [
                        'tier' => $tier,
                        'reach' => $reach,
                        'quantity' => $quantity,
                        'price_paise' => $price,
                        'mrp_paise' => $mrp,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }
        if ($rows !== []) {
            DB::table('job_plan_prices')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
        Schema::dropIfExists('job_plan_prices');
        Schema::dropIfExists('job_credits');
    }
};
