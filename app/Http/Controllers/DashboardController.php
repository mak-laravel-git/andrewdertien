<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where everyone lands after logging in: admins go to the admin dashboard,
 * everyone else to their own.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // Someone who came to toss a ball to a particular person finishes that first.
        if ($username = TossController::pullChosenMember($request)) {
            return redirect()->route('members.show', ['user' => $username, 'toss' => 1]);
        }

        $paid = $user->pondPurchases()->where('status', PurchaseStatus::PAID);

        $asks = User::query()
            ->discoverable()
            ->whereKeyNot($user->id)
            ->where(fn ($query) => $query
                ->where(fn ($ask) => $ask->whereNotNull('dreams')->where('dreams', '!=', ''))
                ->orWhere(fn ($ask) => $ask->whereNotNull('treats')->where('treats', '!=', '')));

        return Inertia::render('pond/member/dashboard', [
            'stats' => [
                'collected' => $user->receivedPurchases()->where('status', PurchaseStatus::PAID)->count(),
                'asks' => (clone $asks)->count(),
                'given_cents' => (int) (clone $paid)->sum('amount_cents'),
                'completed_payments' => (clone $paid)->count(),
            ],
            'currency' => config('payments.currency'),
            'asks' => $asks->inRandomOrder()->limit(3)->get()->map->toRecipient(),
            'balls' => Pond::query()->published()->ordered()->get()->map->toCard(),
        ]);
    }
}
