<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\JobCredit;
use App\Models\PaymentOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A refund, applied: the order is marked refunded and what it bought is
 * taken back (Razorpay refund.processed webhook).
 *
 *  - The watermark-free resume: the entitlement is cleared.
 *  - Job postings: the postings not yet used are withdrawn (the credit is
 *    closed). A posting already live on that credit stays live — it was
 *    published and seen; taking it down is an admin decision, not an
 *    automatic one.
 *
 * Idempotent: a refund reported twice changes nothing the second time.
 */
class PaymentRefunds
{
    /** Refunds the paid order the store knows as [$reference], if there is one. */
    public function refundByReference(string $gateway, string $reference, string $reason): ?PaymentOrder
    {
        $order = PaymentOrder::query()
            ->where('gateway', $gateway)
            ->where('gateway_ref', $reference)
            ->first();

        if ($order === null) {
            return null;
        }

        $this->refund($order, $reason);

        return $order->refresh();
    }

    public function refund(PaymentOrder $order, string $reason): void
    {
        if ($order->status !== PaymentStatus::Paid) {
            return;
        }

        DB::transaction(function () use ($order, $reason) {
            $order->fill([
                'status' => PaymentStatus::Refunded,
                'failure_reason' => $reason,
            ])->save();

            if ($order->plan_id === PaymentService::JOB_PLAN) {
                JobCredit::query()
                    ->where('payment_order_id', $order->id)
                    ->get()
                    ->each(fn (JobCredit $credit) => $credit->forceFill([
                        'total' => $credit->used,
                        'expires_at' => now(),
                    ])->save());

                return;
            }

            $oneOff = PaymentService::oneOffById((string) $order->plan_id);
            if ($oneOff !== null) {
                $order->user()->first()?->candidateProfile()->first()
                    ?->forceFill([$oneOff['grant'] => null])
                    ->save();
            }
        });

        Log::info('[payments] order refunded', ['order' => $order->id, 'gateway' => $order->gateway, 'reason' => $reason]);
    }
}
