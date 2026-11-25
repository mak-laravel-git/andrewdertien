<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\StoreAdvertisingInquiryRequest;
use App\Mail\AdvertisingInquiryReceived;
use App\Models\AdvertisingInquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

/**
 * "Advertise on the Pond": the short inquiry form behind the callout card on Explore.
 */
class AdvertisingInquiryController extends Controller
{
    public function store(StoreAdvertisingInquiryRequest $request): RedirectResponse
    {
        $inquiry = AdvertisingInquiry::create($request->validated());

        $recipient = config('mail.offers_to')
            ?: User::role(RoleEnum::ADMIN->value)->orderBy('id')->value('email');

        if ($recipient !== null) {
            Mail::to($recipient)->send(new AdvertisingInquiryReceived($inquiry));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thanks! We will be in touch about advertising on the Pond.']);

        return back();
    }
}
