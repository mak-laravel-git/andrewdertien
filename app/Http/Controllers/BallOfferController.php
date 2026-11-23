<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\StoreBallOfferRequest;
use App\Mail\BallOfferReceived;
use App\Models\BallOffer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

/**
 * "Make Me an Offer": the contact form on The Deep End.
 */
class BallOfferController extends Controller
{
    public function store(StoreBallOfferRequest $request): RedirectResponse
    {
        $offer = BallOffer::create($request->validated());

        $recipient = $this->recipientAddress();

        if ($recipient !== null) {
            Mail::to($recipient)->send(new BallOfferReceived($offer));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thanks! Your offer is on its way to Andrew.']);

        return back();
    }

    /**
     * The inbox for offers: OFFERS_EMAIL when set, otherwise the first administrator.
     */
    private function recipientAddress(): ?string
    {
        return config('mail.offers_to')
            ?: User::role(RoleEnum::ADMIN->value)->orderBy('id')->value('email');
    }
}
