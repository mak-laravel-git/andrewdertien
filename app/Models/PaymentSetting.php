<?php

namespace App\Models;

use App\Support\Payments\FeeSettings;
use Database\Factories\PaymentSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The payment provider keys an admin entered in the dashboard. There is only
 * ever one row, and the secrets are encrypted at rest.
 */
#[Fillable([
    'stripe_enabled', 'stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret',
    'platform_fee_percent', 'minimum_fee_cents', 'minimum_toss_cents', 'adaptive_pricing', 'minimum_withdrawal_cents', 'wallet_hold_days',
])]
#[Hidden(['stripe_secret_key', 'stripe_webhook_secret'])]
class PaymentSetting extends Model
{
    /** @use HasFactory<PaymentSettingFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // The fee settings are read once per request, so forget them whenever they change.
        static::saved(fn () => app(FeeSettings::class)->flush());
        static::deleted(fn () => app(FeeSettings::class)->flush());
    }

    /**
     * The one settings row, created empty the first time it is needed.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stripe_enabled' => 'boolean',
            'platform_fee_percent' => 'decimal:2',
            'minimum_fee_cents' => 'integer',
            'minimum_toss_cents' => 'integer',
            'adaptive_pricing' => 'boolean',
            'minimum_withdrawal_cents' => 'integer',
            'wallet_hold_days' => 'integer',
            'stripe_secret_key' => 'encrypted',
            'stripe_webhook_secret' => 'encrypted',
        ];
    }
}
