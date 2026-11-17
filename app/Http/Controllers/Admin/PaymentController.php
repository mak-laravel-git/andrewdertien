<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Pond;
use App\Models\PondPurchase;
use App\Support\Payments\PaymentGatewayException;
use App\Support\Payments\PaymentPeriod;
use App\Support\Payments\PondCheckout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Who bought which pond, and every payment behind it.
 */
class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $period = PaymentPeriod::fromRequest($request);
        $status = PurchaseStatus::tryFrom((string) $request->query('status'));

        return Inertia::render('pond/admin/payments', [
            'payments' => $this->filtered($request, $period)
                ->with('pond')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (PondPurchase $purchase): array => [
                    ...$purchase->toHistoryRow(),
                    'gateway_session_id' => $purchase->gateway_session_id,
                    'gateway_payment_id' => $purchase->gateway_payment_id,
                ]),
            'totals' => [
                'paid_cents' => $this->sumFor($request, $period, PurchaseStatus::PAID),
                'refunded_cents' => $this->sumFor($request, $period, PurchaseStatus::REFUNDED),
                'pending' => $this->filtered($request, $period)->where('status', PurchaseStatus::PENDING)->count(),
            ],
            'currency' => config('payments.currency'),
            'ponds' => Pond::withTrashed()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $this->search($request),
                'status' => $status?->value ?? '',
                'pond' => $request->integer('pond') ?: null,
                ...$period->toFilters(),
            ],
        ]);
    }

    /**
     * Download the payments matching the page's period, pond and search as a spreadsheet.
     */
    public function export(Request $request): StreamedResponse
    {
        $period = PaymentPeriod::fromRequest($request);
        $status = PurchaseStatus::tryFrom((string) $request->query('status'));

        $query = $this->filtered($request, $period)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('id');

        $filename = "payments-{$period->slug()}-".now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');

            // The byte order mark lets Excel read names with accents correctly.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, ['Reference', 'Date', 'Paid at', 'Refunded at', 'Pond', 'Member', 'Email', 'Amount', 'Currency', 'Status', 'Mode', 'Gateway', 'Payment ID', 'Note']);

            $query->chunkById(500, function ($purchases) use ($output): void {
                foreach ($purchases as $purchase) {
                    fputcsv($output, array_map($this->safeCell(...), [
                        $purchase->reference,
                        $purchase->created_at?->format('Y-m-d H:i'),
                        $purchase->paid_at?->format('Y-m-d H:i'),
                        $purchase->refunded_at?->format('Y-m-d H:i'),
                        $purchase->pond_name,
                        $purchase->buyer_name,
                        $purchase->buyer_email,
                        number_format($purchase->amount_cents / 100, 2, '.', ''),
                        strtoupper($purchase->currency),
                        $purchase->status->value,
                        $purchase->is_test ? 'Test' : 'Live',
                        $purchase->gateway,
                        $purchase->gateway_payment_id,
                        $purchase->failure_message,
                    ]));
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function refund(PondPurchase $purchase, PondCheckout $checkout): RedirectResponse
    {
        try {
            $checkout->refund($purchase);

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$purchase->reference} refunded."]);
        } catch (PaymentGatewayException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect()->back();
    }

    /**
     * Purchases inside the chosen period that also match the pond and search boxes.
     *
     * @return Builder<PondPurchase>
     */
    private function filtered(Request $request, PaymentPeriod $period): Builder
    {
        $search = $this->search($request);
        $pondId = $request->integer('pond') ?: null;

        return $period->apply(PondPurchase::query())
            ->when($pondId, fn ($query) => $query->where('pond_id', $pondId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('buyer_name', 'like', "%{$search}%")
                        ->orWhere('buyer_email', 'like', "%{$search}%")
                        ->orWhere('pond_name', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            });
    }

    private function sumFor(Request $request, PaymentPeriod $period, PurchaseStatus $status): int
    {
        return (int) $this->filtered($request, $period)->where('status', $status)->sum('amount_cents');
    }

    private function search(Request $request): string
    {
        return trim((string) $request->query('search', ''));
    }

    /**
     * Stop a spreadsheet from running a cell as a formula when a name starts with "=", "+", "-" or "@".
     */
    private function safeCell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
