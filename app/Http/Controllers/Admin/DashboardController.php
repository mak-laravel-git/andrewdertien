<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Enums\PurchaseStatus;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Pond;
use App\Models\PondPurchase;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use App\Support\Payments\PaymentGatewayManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const REVENUE_MONTHS = 6;

    public function __invoke(PaymentGatewayManager $gateways): Response
    {
        $paid = PondPurchase::query()->where('status', PurchaseStatus::PAID);

        return Inertia::render('pond/admin/dashboard', [
            'stats' => [
                'revenue_cents' => (int) (clone $paid)->sum('amount_cents'),
                'revenue_30d_cents' => (int) (clone $paid)->where('paid_at', '>=', now()->subDays(30))->sum('amount_cents'),
                'purchases' => (clone $paid)->count(),
                'members' => User::role(RoleEnum::USER->value)->count(),
                'buyers' => (clone $paid)->whereNotNull('user_id')->distinct()->count('user_id'),
                'ponds' => Pond::count(),
                'ponds_on_frontend' => Pond::query()->onFrontend()->count(),
            ],
            'currency' => config('payments.currency'),
            'money' => $this->money(),
            'attention' => PondPurchase::query()->needingAttention()->latest('id')->limit(5)->get()
                ->map(fn (PondPurchase $purchase): array => [
                    ...$purchase->toHistoryRow(),
                    'payout_failure_message' => $purchase->payout_failure_message,
                ]),
            'revenueByMonth' => $this->revenueByMonth(),
            'recentPayments' => PondPurchase::query()->with('pond')->latest('id')->limit(6)->get()
                ->map(fn (PondPurchase $purchase): array => $purchase->toHistoryRow()),
            'topPonds' => Pond::query()
                ->withCount(['purchases as sales' => fn ($query) => $query->where('status', PurchaseStatus::PAID)])
                ->withSum(['purchases as revenue_cents' => fn ($query) => $query->where('status', PurchaseStatus::PAID)], 'amount_cents')
                ->orderByDesc('sales')
                ->limit(5)
                ->get()
                ->map(fn (Pond $pond): array => [
                    'id' => $pond->id,
                    'name' => $pond->name,
                    'image_url' => $pond->imageUrl(),
                    'sales' => $pond->sales,
                    'revenue_cents' => (int) $pond->revenue_cents,
                ]),
            'gateway' => [
                'driver' => $gateways->driver(),
                'mode' => $gateways->mode(),
                'is_test_mode' => $gateways->isTestMode(),
                'is_ready' => $gateways->isReady(),
            ],
        ]);
    }

    /**
     * Where the money went: what the platform earns, what members are owed in their wallets, what they
     * have withdrawn, and what needs a look. A refunded toss for a member still counts its platform
     * fee, which is kept.
     *
     * @return array{fees_cents: int, pond_cents: int, owed_cents: int, withdrawals_waiting_cents: int, withdrawals_waiting_count: int, withdrawn_cents: int, waiting_cents: int, waiting_count: int, processing_cents: int, failed_payouts: int, open_disputes: int, negative_wallets: int}
     */
    private function money(): array
    {
        $paid = PondPurchase::query()->where('status', PurchaseStatus::PAID);
        $waiting = (clone $paid)->whereNotNull('recipient_id')->whereIn('payout_status', [PayoutStatus::PENDING, PayoutStatus::FAILED]);
        $requests = WithdrawalRequest::query()->where('status', WithdrawalRequest::PENDING);

        return [
            // The fee on every toss for a member that was ever paid, since a refund keeps it.
            'fees_cents' => (int) PondPurchase::query()->whereNotNull('recipient_id')->whereNotNull('paid_at')->sum('fee_cents'),
            // Balls tossed into the Pond belong wholly to the platform.
            'pond_cents' => (int) (clone $paid)->whereNull('recipient_id')->sum('amount_cents'),
            // Everything in members' wallets: money the platform holds for them and still owes.
            'owed_cents' => (int) WalletEntry::query()->sum('amount_cents'),
            'withdrawals_waiting_cents' => (int) (clone $requests)->sum('amount_cents'),
            'withdrawals_waiting_count' => (clone $requests)->count(),
            'withdrawn_cents' => (int) WithdrawalRequest::query()->where('status', WithdrawalRequest::PAID)->sum('amount_cents'),
            // Paid for, but the member's share is not in their wallet yet.
            'waiting_cents' => (int) (clone $waiting)->sum('amount_cents') - (int) (clone $waiting)->sum('fee_cents'),
            'waiting_count' => (clone $waiting)->count(),
            'processing_cents' => (int) PondPurchase::query()->whereNotNull('paid_at')->sum('processing_fee_cents'),
            'failed_payouts' => (clone $paid)->where('payout_status', PayoutStatus::FAILED)->count(),
            'open_disputes' => PondPurchase::query()->openDispute()->count(),
            'negative_wallets' => User::query()->whereRaw('(select coalesce(sum(amount_cents), 0) from wallet_entries where wallet_entries.user_id = users.id) < 0')->count(),
        ];
    }

    /**
     * @return Collection<int, array{month: string, revenue_cents: int}>
     */
    private function revenueByMonth(): Collection
    {
        $firstMonth = CarbonImmutable::now()->startOfMonth()->subMonths(self::REVENUE_MONTHS - 1);

        $totals = PondPurchase::query()
            ->where('status', PurchaseStatus::PAID)
            ->where('paid_at', '>=', $firstMonth)
            ->get(['paid_at', 'amount_cents'])
            ->groupBy(fn (PondPurchase $purchase): string => $purchase->paid_at->format('Y-m'))
            ->map(fn (Collection $purchases): int => (int) $purchases->sum('amount_cents'));

        return collect(range(0, self::REVENUE_MONTHS - 1))->map(function (int $offset) use ($firstMonth, $totals): array {
            $month = $firstMonth->addMonths($offset);

            return ['month' => $month->format('M'), 'revenue_cents' => $totals->get($month->format('Y-m'), 0)];
        });
    }
}
