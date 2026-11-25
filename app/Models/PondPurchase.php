<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Enums\PurchaseStatus;
use Database\Factories\PondPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One attempt by a member to toss a ball, together with its payment. A ball
 * counts once the purchase is in the "paid" status. It can be for a particular
 * member, who then collects it.
 */
#[Fillable([
    'reference', 'user_id', 'recipient_id', 'pond_id', 'buyer_name', 'buyer_email', 'recipient_name', 'pond_name', 'amount_cents',
    'currency', 'presentment_currency', 'presentment_amount_cents', 'fee_percent', 'fee_cents', 'processing_fee_cents', 'net_cents', 'payout_status', 'transfer_id',
    'payout_failure_message', 'payout_attempts', 'credited_at', 'refunded_cents',
    'dispute_id', 'dispute_status', 'disputed_at', 'status', 'gateway', 'gateway_session_id', 'gateway_payment_id', 'is_test',
    'failure_message', 'paid_at', 'refunded_at',
])]
class PondPurchase extends Model
{
    /** @use HasFactory<PondPurchaseFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payout_status' => 'none',
        'payout_attempts' => 0,
        'fee_cents' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (PondPurchase $purchase): void {
            $purchase->reference ??= 'AUP-'.Str::upper(Str::random(10));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The member the ball was tossed to, if it was for someone in particular.
     *
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<Pond, $this>
     */
    public function pond(): BelongsTo
    {
        return $this->belongsTo(Pond::class)->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === PurchaseStatus::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === PurchaseStatus::PAID;
    }

    /**
     * Whether the buyer paid in a currency other than the one the ball is priced in.
     */
    public function paidInOtherCurrency(): bool
    {
        return $this->presentment_currency !== null
            && $this->presentment_amount_cents !== null
            && strtolower($this->presentment_currency) !== strtolower($this->currency);
    }

    /**
     * An amount in the currency the buyer paid in (what the provider reports on refunds and disputes),
     * turned back into the currency the ball is priced in.
     */
    public function toPricedCents(int $buyerCents): int
    {
        return $this->paidInOtherCurrency() && $this->presentment_amount_cents > 0
            ? (int) round($buyerCents * $this->amount_cents / $this->presentment_amount_cents)
            : $buyerCents;
    }

    /**
     * An amount in the currency the ball is priced in, turned into the currency the buyer paid in.
     */
    public function toBuyerCents(int $pricedCents): int
    {
        return $this->paidInOtherCurrency() && $this->amount_cents > 0
            ? (int) round($this->presentment_amount_cents * $pricedCents / $this->amount_cents)
            : $pricedCents;
    }

    /**
     * Purchases an admin should look at: a payout that keeps failing, or a dispute that is still open.
     *
     * @param  Builder<PondPurchase>  $query
     */
    public function scopeNeedingAttention(Builder $query): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->where(fn (Builder $failed) => $failed->where('status', PurchaseStatus::PAID)->where('payout_status', PayoutStatus::FAILED))
            ->orWhere(fn (Builder $disputed) => $disputed->openDispute()));
    }

    /**
     * Purchases whose dispute has not been settled yet.
     *
     * @param  Builder<PondPurchase>  $query
     */
    public function scopeOpenDispute(Builder $query): void
    {
        $query->whereNotNull('dispute_id')->where(fn (Builder $inner) => $inner
            ->whereNull('dispute_status')
            ->orWhereNotIn('dispute_status', ['won', 'lost', 'warning_closed', 'charge_refunded']));
    }

    /**
     * What the buyer gets back if this is refunded: everything except the platform fee, which is kept.
     */
    public function refundAmountCents(): int
    {
        return max(0, $this->amount_cents - $this->fee_cents);
    }

    /**
     * Whether the buyer's bank has a dispute open on this payment that is not settled yet.
     */
    public function hasOpenDispute(): bool
    {
        return $this->dispute_id !== null && ! in_array($this->dispute_status, ['won', 'lost', 'warning_closed', 'charge_refunded'], true);
    }

    /**
     * The fields shown on a row of any purchase or payment history.
     *
     * @return array{reference: string, user_id: int|null, buyer_name: string, buyer_email: string, recipient_id: int|null, recipient_name: string|null, recipient_username: string|null, pond_name: string, pond_slug: string|null, amount_cents: int, fee_cents: int, processing_fee_cents: int|null, net_cents: int|null, payout_status: string, refund_cents: int, refunded_cents: int|null, dispute_status: string|null, currency: string, presentment_currency: string|null, presentment_amount_cents: int|null, status: string, gateway: string, is_test: bool, failure_message: string|null, created_at: string|null, paid_at: string|null, refunded_at: string|null}
     */
    public function toHistoryRow(): array
    {
        return [
            'reference' => $this->reference,
            'user_id' => $this->user_id,
            'buyer_name' => $this->buyer_name,
            'buyer_email' => $this->buyer_email,
            'recipient_id' => $this->recipient_id,
            'recipient_name' => $this->recipient_name,
            'recipient_username' => $this->recipient?->username,
            'pond_name' => $this->pond_name,
            'pond_slug' => $this->pond?->slug,
            'amount_cents' => $this->amount_cents,
            'fee_cents' => $this->fee_cents,
            'processing_fee_cents' => $this->processing_fee_cents,
            'net_cents' => $this->net_cents,
            'payout_status' => $this->payout_status->value,
            'refund_cents' => $this->refundAmountCents(),
            'refunded_cents' => $this->refunded_cents,
            'dispute_status' => $this->dispute_status,
            'currency' => $this->currency,
            'presentment_currency' => $this->paidInOtherCurrency() ? $this->presentment_currency : null,
            'presentment_amount_cents' => $this->paidInOtherCurrency() ? $this->presentment_amount_cents : null,
            'status' => $this->status->value,
            'gateway' => $this->gateway,
            'is_test' => $this->is_test,
            'failure_message' => $this->failure_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'fee_percent' => 'decimal:2',
            'fee_cents' => 'integer',
            'processing_fee_cents' => 'integer',
            'net_cents' => 'integer',
            'payout_status' => PayoutStatus::class,
            'payout_attempts' => 'integer',
            'credited_at' => 'datetime',
            'refunded_cents' => 'integer',
            'presentment_amount_cents' => 'integer',
            'disputed_at' => 'datetime',
            'is_test' => 'boolean',
            'status' => PurchaseStatus::class,
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
