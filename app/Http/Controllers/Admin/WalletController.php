<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use App\Support\Wallet\Wallet;
use App\Support\Wallet\WalletException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every member's wallet: who is owed what, the ledger behind each balance, and a way for an
 * admin to put a balance right by hand (with a reason that is kept).
 */
class WalletController extends Controller
{
    /** A member's balance, worked out in the query so members can be filtered by it. */
    private const BALANCE_SQL = '(select coalesce(sum(amount_cents), 0) from wallet_entries where wallet_entries.user_id = users.id)';

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $owing = $request->boolean('owing');

        $members = User::query()
            ->whereHas('walletEntries')
            ->withSum('walletEntries as total_cents', 'amount_cents')
            ->withSum(['walletEntries as available_cents' => fn (Builder $query) => $query->where('available_at', '<=', now())], 'amount_cents')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($owing, fn (Builder $query) => $query->whereRaw(self::BALANCE_SQL.' < 0'))
            ->orderByDesc('total_cents')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'username' => $member->username,
                'total_cents' => (int) $member->total_cents,
                'available_cents' => (int) $member->available_cents,
            ]);

        return Inertia::render('pond/admin/wallets', [
            'members' => $members,
            'totals' => [
                'owed_cents' => (int) WalletEntry::query()->sum('amount_cents'),
                'negative_count' => User::query()->whereRaw(self::BALANCE_SQL.' < 0')->count(),
            ],
            'currency' => config('payments.currency'),
            'filters' => ['search' => $search, 'owing' => $owing],
        ]);
    }

    public function show(User $user, Wallet $wallet): Response
    {
        return Inertia::render('pond/admin/wallet', [
            'member' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'username' => $user->username],
            'wallet' => $wallet->summary($user),
            'entries' => WalletEntry::query()->where('user_id', $user->id)->with('createdBy')->latest('id')->limit(100)->get()
                ->map(fn (WalletEntry $entry): array => [...$entry->toRow(), 'by' => $entry->createdBy?->name]),
            'withdrawals' => WithdrawalRequest::query()->where('user_id', $user->id)->latest('id')->limit(20)->get()->map->toMemberRow(),
        ]);
    }

    /**
     * Put a member's balance right by hand. Positive adds money, negative takes it away.
     */
    public function adjust(Request $request, User $user, Wallet $wallet): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'not_in:0', 'between:-100000,100000'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'amount.required' => 'Enter an amount to add (or a minus amount to take away).',
            'amount.not_in' => 'Enter an amount to add (or a minus amount to take away).',
            'reason.required' => 'Write the reason, so there is a record of it.',
        ]);

        try {
            $wallet->adjust($user, (int) round(((float) $data['amount']) * 100), $data['reason'], $request->user());

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name}'s wallet has been adjusted."]);
        } catch (WalletException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }
}
