<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreWithdrawalRequest;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use App\Support\Payments\FeeSettings;
use App\Support\Wallet\Wallet;
use App\Support\Wallet\WalletException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A member's wallet: what balls tossed to them have earned, what they can withdraw, and their
 * withdrawal requests. An admin pays those out by hand.
 */
class WalletController extends Controller
{
    public function __construct(private Wallet $wallet) {}

    public function index(Request $request, FeeSettings $fees): Response
    {
        $user = $request->user();

        return Inertia::render('pond/member/wallet', [
            'wallet' => $this->wallet->summary($user),
            'entries' => WalletEntry::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(30)
                ->get()
                ->map->toRow(),
            'withdrawals' => WithdrawalRequest::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(15)
                ->get()
                ->map->toMemberRow(),
            'rules' => [
                'minimum_withdrawal_cents' => $fees->minimumWithdrawalCents(),
                'hold_days' => $fees->walletHoldDays(),
                'can_withdraw' => $user->canWithdraw(),
                'needs_birth_date' => $user->date_of_birth === null,
            ],
            'methods' => collect(WithdrawalRequest::METHODS)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values(),
        ]);
    }

    /**
     * Ask for money in the wallet to be paid out.
     */
    public function withdraw(StoreWithdrawalRequest $request): RedirectResponse
    {
        try {
            $withdrawal = $this->wallet->requestWithdrawal(
                $request->user(),
                $request->amountCents(),
                $request->validated('method'),
                $request->validated('details'),
                $request->validated('note'),
            );
        } catch (WalletException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Withdrawal {$withdrawal->reference} requested. We will pay it out and let you know."]);

        return to_route('member.wallet.index');
    }

    /**
     * Take back a request that has not been paid yet.
     */
    public function cancel(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        abort_unless($withdrawal->user_id === $request->user()->id, 404);

        try {
            $this->wallet->cancelWithdrawal($withdrawal);

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Withdrawal cancelled. The money is back in your wallet.']);
        } catch (WalletException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return to_route('member.wallet.index');
    }
}
