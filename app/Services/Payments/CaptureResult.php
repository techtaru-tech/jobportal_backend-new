<?php

namespace App\Services\Payments;

/**
 * What a gateway says about one capture attempt.
 *
 * Deliberately not a bare bool: a failure that cannot explain itself leaves
 * the payer staring at "payment failed" with nothing to do next.
 */
final readonly class CaptureResult
{
    private function __construct(
        public bool $successful,
        public ?string $reference,
        public ?string $failureReason,
    ) {}

    public static function paid(?string $reference = null): self
    {
        return new self(true, $reference, null);
    }

    public static function failed(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
