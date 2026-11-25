<?php

namespace App\Models;

use Database\Factories\WithdrawalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A member asking for money in their wallet to be paid out to them. An admin pays it by hand
 * (bank transfer, PayPal and so on) and then marks it paid, or turns it down with a reason.
 *
 * @property int $id
 * @property string $reference
 * @property int $user_id
 * @property int $amount_cents
 * @property string $currency
 * @property string $method
 * @property string $details
 * @property string $status
 */
#[Fillable([
    'reference', 'user_id', 'amount_cents', 'currency', 'method', 'details', 'note', 'status',
    'admin_note', 'payment_reference', 'processed_by', 'processed_at',
])]
class WithdrawalRequest extends Model
{
    /** @use HasFactory<WithdrawalRequestFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    /**
     * How a member can ask to be paid.
     *
     * @var array<string, string>
     */
    public const METHODS = [
        'bank' => 'Bank transfer',
        'paypal' => 'PayPal',
        'other' => 'Other',
    ];

    protected static function booted(): void
    {
        static::creating(function (WithdrawalRequest $request): void {
            $request->reference ??= 'WD-'.Str::upper(Str::random(8));
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
     * @return BelongsTo<User, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return HasMany<WalletEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * What a member sees about their own request. The payout details are left out: they are
     * only ever shown to the admin who pays it.
     *
     * @return array{id: int, reference: string, amount_cents: int, currency: string, method: string, method_label: string, status: string, note: string|null, admin_note: string|null, payment_reference: string|null, created_at: string|null, processed_at: string|null}
     */
    public function toMemberRow(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'method' => $this->method,
            'method_label' => self::METHODS[$this->method] ?? $this->method,
            'status' => $this->status,
            'note' => $this->note,
            'admin_note' => $this->admin_note,
            'payment_reference' => $this->payment_reference,
            'created_at' => $this->created_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'details' => 'encrypted',
            'processed_at' => 'datetime',
        ];
    }
}
