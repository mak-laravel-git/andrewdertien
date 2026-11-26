<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvertisingInquiry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Enquiries sent from the "Advertise on the Pond" card on Explore: an admin reads them here,
 * replies by email, and marks each one handled.
 */
class AdvertisingInquiryController extends Controller
{
    private const NEW = 'new';

    private const HANDLED = 'handled';

    public function index(Request $request): Response
    {
        $status = $this->status($request);
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('pond/admin/inquiries', [
            'inquiries' => AdvertisingInquiry::query()
                ->when($status === self::NEW, fn (Builder $query) => $query->whereNull('handled_at'))
                ->when($status === self::HANDLED, fn (Builder $query) => $query->whereNotNull('handled_at'))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $like = '%'.addcslashes($search, '%_\\').'%';

                    $query->where(fn (Builder $inner) => $inner
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('company', 'like', $like)
                        ->orWhere('message', 'like', $like));
                })
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (AdvertisingInquiry $inquiry): array => [
                    'id' => $inquiry->id,
                    'name' => $inquiry->name,
                    'email' => $inquiry->email,
                    'company' => $inquiry->company,
                    'website' => $inquiry->website,
                    'message' => $inquiry->message,
                    'received_at' => $inquiry->created_at?->toIso8601String(),
                    'handled_at' => $inquiry->handled_at?->toIso8601String(),
                ]),
            'totals' => [
                'new' => AdvertisingInquiry::query()->whereNull('handled_at')->count(),
                'handled' => AdvertisingInquiry::query()->whereNotNull('handled_at')->count(),
            ],
            'filters' => ['status' => $status ?? '', 'search' => $search],
        ]);
    }

    /**
     * Mark an enquiry handled, or put it back among the new ones.
     */
    public function update(Request $request, AdvertisingInquiry $inquiry): RedirectResponse
    {
        $handled = $request->boolean('handled', ! $inquiry->isHandled());

        $inquiry->forceFill(['handled_at' => $handled ? now() : null])->save();

        return back();
    }

    private function status(Request $request): ?string
    {
        $status = $request->query('status');

        return in_array($status, [self::NEW, self::HANDLED], true) ? $status : null;
    }
}
