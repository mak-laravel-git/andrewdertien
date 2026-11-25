<?php

namespace App\Models;

use Database\Factories\WalletEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a member's wallet ledger. A member's balance is the sum of their lines, so
 * nothing is ever edited: a mistake is put right with a new line.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int $amount_cents
 * @property string $currency
 * @property string $description
 */
#[Fillable([
    'user_id', 'type', 'amount_cents', 'currency', 'available_at', 'purchase_id', 'withdrawal_request_id', 'description', 'created_by',
])]
class WalletEntry extends Model
{
    /** @use HasFactory<WalletEntryFactory> */
    use HasFactory;

    /** The member's share of a toss, added once it was paid for. */
    public const TOSS_RECEIVED = 'toss_received';

    /** The member's share taken back because the toss was refunded or lost in a dispute. */
    public const REFUND_REVERSAL = 'refund_reversal';

    /** Money set aside for a withdrawal request, so it cannot be spent twice. */
    public const WITHDRAWAL_HOLD = 'withdrawal_hold';

    /** Money set aside for a withdrawal that was cancelled or turned down, given back. */
    public const WITHDRAWAL_RELEASED = 'withdrawal_released';

    /** An admin putting the balance right by hand, with a reason. */
    public const ADJUSTMENT = 'adjustment';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PondPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PondPurchase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether this money can be withdrawn yet (earnings can be held for a few days).
     */
    public function isAvailable(): bool
    {
        return $this->available_at !== null && $this->available_at->lte(now());
    }

    /**
     * @return array{id: int, type: string, amount_cents: int, currency: string, description: string, created_at: string|null, available_at: string|null, is_available: bool}
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'available_at' => $this->available_at?->toIso8601String(),
            'is_available' => $this->isAvailable(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'available_at' => 'datetime',
        ];
    }
}
