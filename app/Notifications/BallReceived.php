<?php

namespace App\Notifications;

use App\Models\PondPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a member that someone has tossed them a ball: in the bell, on their dashboard and by email.
 */
class BallReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PondPurchase $purchase)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("{$this->purchase->buyer_name} tossed you a ball!")
            ->greeting("Hi {$this->firstName($notifiable->name)}!")
            ->line("{$this->purchase->buyer_name} tossed you a {$this->purchase->pond_name} worth {$this->amount()}.");

        if ($this->purchase->net_cents !== null) {
            $mail->line($this->money($this->purchase->net_cents).' has been added to your wallet, after the platform fee and payment processing fee.');
        }

        return $mail
            ->action('See your balls', route('member.ponds.owned'))
            ->line('You can ask to withdraw what is in your wallet at any time.')
            ->line('Thank you for being part of Ask Upon A Pond.');
    }

    /**
     * @return array{type: string, message: string, url: string, purchase: string, amount_cents: int, currency: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ball_received',
            'message' => "{$this->purchase->buyer_name} tossed you a {$this->purchase->pond_name} worth {$this->amount()}.",
            'url' => route('member.ponds.owned', absolute: false),
            'purchase' => $this->purchase->reference,
            'amount_cents' => $this->purchase->amount_cents,
            'currency' => $this->purchase->currency,
        ];
    }

    private function amount(): string
    {
        return $this->money($this->purchase->amount_cents);
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }

    private function firstName(string $name): string
    {
        return explode(' ', $name)[0];
    }
}
