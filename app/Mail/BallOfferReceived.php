<?php

namespace App\Mail;

use App\Models\BallOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the site owner when a visitor makes an offer from "The Deep End".
 */
class BallOfferReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BallOffer $offer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->offer->email, $this->offer->name)],
            subject: "Deep End offer from {$this->offer->name}",
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.ball-offer');
    }
}
