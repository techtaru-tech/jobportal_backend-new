<?php

namespace App\Enums;

/**
 * What the payer picked on the payment-method screen.
 *
 * Display-only as far as this server is concerned — the gateway decides how
 * the money actually moves. It is recorded because "which methods do people
 * actually use" is the first question anyone asks of a payments table, and it
 * cannot be answered retroactively.
 */
enum PaymentMethod: string
{
    case Upi = 'upi';
    case Card = 'card';
    case NetBanking = 'netbanking';

    /** Google Play Billing — the store sheet on Android. */
    case GooglePlay = 'google_play';

    /** Apple In-App Purchase — the store sheet on iOS. */
    case AppStore = 'app_store';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            self::Card => 'Card',
            self::NetBanking => 'Net banking',
            self::GooglePlay => 'Google Play',
            self::AppStore => 'App Store',
        };
    }

    /** Paid through a store's own sheet rather than a payment gateway. */
    public function isStore(): bool
    {
        return $this === self::GooglePlay || $this === self::AppStore;
    }

    /** The store method for [$platform] (`android` / `ios`), if any. */
    public static function storeFor(?string $platform): ?self
    {
        return match ($platform) {
            'android' => self::GooglePlay,
            'ios' => self::AppStore,
            default => null,
        };
    }

    /** The store method for [$platform], only when that store is switched on. */
    public static function enabledStoreFor(?string $platform): ?self
    {
        $store = self::storeFor($platform);

        return $store !== null && config("iap.enabled.{$platform}") ? $store : null;
    }

    /** UPI / card / net banking — what is offered when no store is. */
    public static function gatewayMethods(): array
    {
        return array_values(array_filter(self::cases(), fn (self $m) => ! $m->isStore()));
    }
}
