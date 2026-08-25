<?php

namespace App\Providers;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\TestGateway;
use Illuminate\Support\ServiceProvider;

/**
 * The single place that decides which gateway is live.
 *
 * Adding Razorpay is: write `RazorpayGateway implements PaymentGateway`, add
 * its case to the match below, set `PAYMENT_GATEWAY=razorpay`. Nothing else in
 * the codebase names a provider.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function () {
            return match (config('plans.payment_gateway', 'test')) {
                default => new TestGateway(),
            };
        });
    }
}
