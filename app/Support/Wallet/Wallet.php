<?php

namespace App\Support\Wallet;

use App\Models\PondPurchase;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use App\Notifications\WithdrawalProcessed;
use App\Support\Payments\FeeSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Members' wallets. A wallet is a ledger: the balance is the sum of its lines, so every change is
 * a new line and nothing is ever edited. A member's share of each toss is added here, and an admin
 * pays out what the member asks to withdraw by hand.
 */
class Wallet
{
    public function __construct(private FeeSettings $fees) {}

    /**
     * Everything in a wallet: money that can be withdrawn now, money still being held, and what the
     * member has earned and had paid out so far.
     *
     * @return array{total_cents: int, available_cents: int, pending_cents: int, withdrawable_cents: int, earned_cents: int, withdrawn_cents: int, requested_cents: int, currency: string}
     */
    public function summary(User $user): array
    {
        $entries = WalletEntry::query()->where('user_id', $user->id);

        $total = (int) (clone $entries)->sum('amount_cents');
        $available = (int) (clone $entries)->where('available_at', '<=', now())->sum('amount_cents');

        return [
            'total_cents' => $total,
            'available_cents' => $available,
            // Earnings that are still held for a few days before they can be withdrawn.
            'pending_cents' => $total - $available,
            'withdrawable_cents' => max(0, $available),
            'earned_cents' => (int) (clone $entries)->whereIn('type', [WalletEntry::TOSS_RECEIVED, WalletEntry::REFUND_REVERSAL])->sum('amount_cents'),
            'withdrawn_cents' => (int) WithdrawalRequest::query()->where('user_id', $user->id)->where('status', WithdrawalRequest::PAID)->sum('amount_cents'),
            'requested_cents' => (int) WithdrawalRequest::query()->where('user_id', $user->id)->where('status', WithdrawalRequest::PENDING)->sum('amount_cents'),
            'currency' => (string) config('payments.currency'),
        ];
    }

    /**
     * Add the member's share of a paid toss. A purchase is only ever credited once.
     */
    public function creditPurchase(PondPurchase $purchase, int $netCents): WalletEntry
    {
        return WalletEntry::query()->firstOrCreate(
            ['purchase_id' => $purchase->id, 'type' => WalletEntry::TOSS_RECEIVED],
            [
                'user_id' => $purchase->recipient_id,
                'amount_cents' => $netCents,
                'currency' => $purchase->currency,
                'available_at' => now()->addDays($this->fees->walletHoldDays()),
                'description' => "{$purchase->pond_name} from {$purchase->buyer_name}",
            ],
        );
    }

    /**
     * Take back the member's share of a toss that was refunded or lost in a dispute. The wallet can
     * go below zero if the member already withdrew the money; later earnings then pay it off.
     */
    public function reversePurchase(PondPurchase $purchase): ?WalletEntry
    {
        $credited = WalletEntry::query()
            ->where('purchase_id', $purchase->id)
            ->where('type', WalletEntry::TOSS_RECEIVED)
            ->first();

        if (! $credited) {
            return null;
        }

        return WalletEntry::query()->firstOrCreate(
            ['purchase_id' => $purchase->id, 'type' => WalletEntry::REFUND_REVERSAL],
            [
                'user_id' => $credited->user_id,
                'amount_cents' => -$credited->amount_cents,
                'currency' => $credited->currency,
                'available_at' => now(),
                'description' => "Refund of {$purchase->pond_name} from {$purchase->buyer_name}",
            ],
        );
    }

    /**
     * A member asks for money to be paid out. The money is set aside at once so it cannot be spent
     * twice, and an admin pays it by hand.
     *
     * @throws WalletException
     */
    public function requestWithdrawal(User $user, int $amountCents, string $method, string $details, ?string $note = null): WithdrawalRequest
    {
        if (! $user->isAdult()) {
            throw new WalletException('Withdrawals are for members aged 18 or older. Add your date of birth in My Account first.');
        }

        if (! array_key_exists($method, WithdrawalRequest::METHODS)) {
            throw new WalletException('Choose how you would like to be paid.');
        }

        if (trim($details) === '') {
            throw new WalletException('Tell us where to send the money.');
        }

        return DB::transaction(function () use ($user, $amountCents, $method, $details, $note): WithdrawalRequest {
            // Two requests at once cannot both spend the same money.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $summary = $this->summary($user);
            $minimum = $this->fees->minimumWithdrawalCents();

            if ($amountCents < $minimum) {
                throw new WalletException('The smallest withdrawal is $'.number_format($minimum / 100, 2).'.');
            }

            if ($amountCents > $summary['withdrawable_cents']) {
                throw new WalletException('You can withdraw up to $'.number_format($summary['withdrawable_cents'] / 100, 2).' right now.');
            }

            $request = WithdrawalRequest::query()->create([
                'user_id' => $user->id,
                'amount_cents' => $amountCents,
                'currency' => $summary['currency'],
                'method' => $method,
                'details' => trim($details),
                'note' => filled($note) ? trim((string) $note) : null,
                'status' => WithdrawalRequest::PENDING,
            ]);

            $this->entry($user, WalletEntry::WITHDRAWAL_HOLD, -$amountCents, "Withdrawal {$request->reference}", $request);

            return $request;
        });
    }

    /**
     * A member takes back a request an admin has not paid yet.
     *
     * @throws WalletException
     */
    public function cancelWithdrawal(WithdrawalRequest $request): void
    {
        $this->settleRequest($request, WithdrawalRequest::CANCELLED, null, null, release: true);
    }

    /**
     * An admin has paid the money out by hand.
     *
     * @throws WalletException
     */
    public function markPaid(WithdrawalRequest $request, User $admin, string $paymentReference, ?string $adminNote = null): void
    {
        $this->settleRequest($request, WithdrawalRequest::PAID, $admin, $adminNote, release: false, paymentReference: trim($paymentReference));
    }

    /**
     * An admin turns a request down, with a reason. The money goes back to the member's wallet.
     *
     * @throws WalletException
     */
    public function reject(WithdrawalRequest $request, User $admin, string $reason): void
    {
        if (trim($reason) === '') {
            throw new WalletException('Tell the member why, so they know what to do next.');
        }

        $this->settleRequest($request, WithdrawalRequest::REJECTED, $admin, trim($reason), release: true);
    }

    /**
     * An admin putting a member's balance right by hand, with a reason. Positive adds, negative takes away.
     *
     * @throws WalletException
     */
    public function adjust(User $member, int $signedCents, string $reason, User $admin): WalletEntry
    {
        if ($signedCents === 0) {
            throw new WalletException('Enter an amount to add or take away.');
        }

        if (trim($reason) === '') {
            throw new WalletException('Write the reason, so there is a record of it.');
        }

        return $this->entry($member, WalletEntry::ADJUSTMENT, $signedCents, trim($reason), null, $admin);
    }

    /**
     * @throws WalletException
     */
    private function settleRequest(WithdrawalRequest $request, string $status, ?User $admin, ?string $adminNote, bool $release, ?string $paymentReference = null): void
    {
        DB::transaction(function () use ($request, $status, $admin, $adminNote, $release, $paymentReference): void {
            $current = WithdrawalRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $current->isPending()) {
                throw new WalletException('This request has already been dealt with.');
            }

            if ($status === WithdrawalRequest::PAID && blank($paymentReference)) {
                throw new WalletException('Enter the payment reference or transaction ID, so there is a record of it.');
            }

            $current->update([
                'status' => $status,
                'admin_note' => $adminNote,
                'payment_reference' => $paymentReference,
                'processed_by' => $admin?->id,
                'processed_at' => now(),
            ]);

            if ($release) {
                $this->entry($current->user, WalletEntry::WITHDRAWAL_RELEASED, $current->amount_cents, "Withdrawal {$current->reference} ".($status === WithdrawalRequest::CANCELLED ? 'cancelled' : 'not paid'), $current);
            }

        });

        $request->refresh();

        // Only an admin's decision is news to the member.
        if ($admin) {
            $request->user->notify(new WithdrawalProcessed($request));
        }
    }

    private function entry(User $user, string $type, int $amountCents, string $description, ?WithdrawalRequest $request = null, ?User $by = null, ?Carbon $availableAt = null): WalletEntry
    {
        return WalletEntry::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'amount_cents' => $amountCents,
            'currency' => (string) config('payments.currency'),
            'available_at' => $availableAt ?? now(),
            'withdrawal_request_id' => $request?->id,
            'description' => $description,
            'created_by' => $by?->id,
        ]);
    }
}
