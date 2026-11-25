<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Support\Wallet\Wallet;
use App\Support\Wallet\WalletException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Withdrawal requests: members asking for money in their wallet. An admin pays each one by hand
 * (bank transfer, PayPal and so on) and then marks it paid here, or turns it down with a reason.
 */
class WithdrawalController extends Controller
{
    private const STATUSES = [
        WithdrawalRequest::PENDING => 'Waiting',
        WithdrawalRequest::PAID => 'Paid',
        WithdrawalRequest::REJECTED => 'Turned down',
        WithdrawalRequest::CANCELLED => 'Cancelled',
    ];

    public function index(Request $request, Wallet $wallet): Response
    {
        $status = $this->status($request);

        return Inertia::render('pond/admin/withdrawals', [
            'withdrawals' => $this->filtered($request)
                ->with('user')
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (WithdrawalRequest $withdrawal): array => [
                    ...$withdrawal->toMemberRow(),
                    // Only an admin paying it out ever sees where the money is going.
                    'details' => $withdrawal->details,
                    'member' => [
                        'id' => $withdrawal->user->id,
                        'name' => $withdrawal->user->name,
                        'email' => $withdrawal->user->email,
                        'wallet_total_cents' => $wallet->summary($withdrawal->user)['total_cents'],
                    ],
                ]),
            'totals' => [
                'waiting_count' => WithdrawalRequest::query()->where('status', WithdrawalRequest::PENDING)->count(),
                'waiting_cents' => (int) WithdrawalRequest::query()->where('status', WithdrawalRequest::PENDING)->sum('amount_cents'),
                'paid_cents' => (int) WithdrawalRequest::query()->where('status', WithdrawalRequest::PAID)->sum('amount_cents'),
            ],
            'currency' => config('payments.currency'),
            'statuses' => collect(self::STATUSES)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
            'filters' => ['status' => $status ?? '', 'search' => $this->search($request)],
        ]);
    }

    /**
     * Download the requests on the page as a spreadsheet, including where each is to be paid.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('user')->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, ['Reference', 'Requested', 'Member', 'Email', 'Amount', 'Currency', 'Method', 'Pay to', 'Status', 'Payment reference', 'Admin note', 'Processed at']);

            $query->chunkById(500, function ($withdrawals) use ($output): void {
                foreach ($withdrawals as $withdrawal) {
                    fputcsv($output, array_map($this->safeCell(...), [
                        $withdrawal->reference,
                        $withdrawal->created_at?->format('Y-m-d H:i'),
                        $withdrawal->user->name,
                        $withdrawal->user->email,
                        number_format($withdrawal->amount_cents / 100, 2, '.', ''),
                        strtoupper($withdrawal->currency),
                        WithdrawalRequest::METHODS[$withdrawal->method] ?? $withdrawal->method,
                        $withdrawal->details,
                        $withdrawal->status,
                        $withdrawal->payment_reference,
                        $withdrawal->admin_note,
                        $withdrawal->processed_at?->format('Y-m-d H:i'),
                    ]));
                }
            });

            fclose($output);
        }, 'withdrawals-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The money has been paid out by hand.
     */
    public function pay(Request $request, WithdrawalRequest $withdrawal, Wallet $wallet): RedirectResponse
    {
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ], ['payment_reference.required' => 'Enter the payment reference or transaction ID, so there is a record of it.']);

        try {
            $wallet->markPaid($withdrawal, $request->user(), $data['payment_reference'], $data['admin_note'] ?? null);

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$withdrawal->reference} marked as paid. {$withdrawal->user->name} has been told."]);
        } catch (WalletException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * Turn the request down. The money goes back to the member's wallet and they are told why.
     */
    public function reject(Request $request, WithdrawalRequest $withdrawal, Wallet $wallet): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], ['reason.required' => 'Tell the member why, so they know what to do next.']);

        try {
            $wallet->reject($withdrawal, $request->user(), $data['reason']);

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$withdrawal->reference} turned down. The money is back in {$withdrawal->user->name}'s wallet."]);
        } catch (WalletException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * @return Builder<WithdrawalRequest>
     */
    private function filtered(Request $request): Builder
    {
        $status = $this->status($request);
        $search = $this->search($request);

        return WithdrawalRequest::query()
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('reference', 'like', $term)
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            });
    }

    /**
     * The status to show: what was asked for, "all" when it is left empty, and the requests still
     * waiting to be paid when nothing was asked for.
     */
    private function status(Request $request): ?string
    {
        if (! $request->has('status')) {
            return WithdrawalRequest::PENDING;
        }

        $status = (string) $request->query('status');

        return array_key_exists($status, self::STATUSES) ? $status : null;
    }

    private function search(Request $request): string
    {
        return trim((string) $request->query('search', ''));
    }

    private function safeCell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
