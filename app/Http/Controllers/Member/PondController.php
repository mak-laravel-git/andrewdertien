<?php

namespace App\Http\Controllers\Member;

use App\Enums\PondCategory;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Pond;
use App\Models\PondPurchase;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Balls: browsing the ball types, and what a member has tossed and collected.
 */
class PondController extends Controller
{
    /**
     * Every published ball a member can choose, including ones hidden from the public site.
     * Passing ?to=username starts a toss for that member.
     */
    public function index(Request $request): Response
    {
        $category = PondCategory::tryFrom((string) $request->query('category'));

        $recipient = User::query()
            ->discoverable()
            ->whereKeyNot($request->user()->id)
            ->where('username', (string) $request->query('to'))
            ->first();

        return Inertia::render('pond/member/ponds', [
            'ponds' => Pond::query()
                ->published()
                ->when($category, fn ($query) => $query->where('category', $category))
                ->ordered()
                ->get()
                ->map->toCard(),
            'recipient' => $recipient?->toRecipient(),
            'categories' => collect(PondCategory::cases())
                ->map(fn (PondCategory $option): array => ['value' => $option->value, 'label' => $option->label()]),
            'filters' => ['category' => $category?->value ?? ''],
        ]);
    }

    /**
     * My Balls: the balls this member has tossed, and the ones other members tossed to them.
     */
    public function owned(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('pond/member/my-ponds', [
            'tossed' => $user->pondPurchases()
                ->with(['pond', 'recipient'])
                ->where('status', PurchaseStatus::PAID)
                ->latest('paid_at')
                ->get()
                ->map(fn (PondPurchase $purchase): array => [
                    ...$purchase->toHistoryRow(),
                    'pond' => $purchase->pond->toCard(),
                ]),
            'collected' => $user->receivedPurchases()
                ->with(['pond', 'recipient'])
                ->where('status', PurchaseStatus::PAID)
                ->latest('paid_at')
                ->get()
                ->map(fn (PondPurchase $purchase): array => [
                    ...$purchase->toHistoryRow(),
                    'pond' => $purchase->pond->toCard(),
                ]),
        ]);
    }
}
