<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a member what an admin decided about their withdrawal request: paid, or not paid and why.
 */
class WithdrawalProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WithdrawalRequest $withdrawal)
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
            ->subject($this->isPaid() ? "Your withdrawal of {$this->amount()} was paid" : "Your withdrawal of {$this->amount()} was not paid")
            ->greeting('Hi '.explode(' ', $notifiable->name)[0].'!');

        if ($this->isPaid()) {
            $mail->line("We paid out {$this->amount()} ({$this->withdrawal->reference}) by {$this->method()}.");

            if (filled($this->withdrawal->payment_reference)) {
                $mail->line("Payment reference: {$this->withdrawal->payment_reference}");
            }
        } else {
            $mail->line("We could not pay out {$this->amount()} ({$this->withdrawal->reference}).")
                ->line("Reason: {$this->withdrawal->admin_note}")
                ->line('The money is back in your wallet.');
        }

        return $mail->action('Open my wallet', route('member.wallet.index'));
    }

    /**
     * @return array{type: string, message: string, url: string, withdrawal: string, amount_cents: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'withdrawal_processed',
            'message' => $this->isPaid()
                ? "Your withdrawal of {$this->amount()} was paid."
                : "Your withdrawal of {$this->amount()} was not paid: {$this->withdrawal->admin_note}",
            'url' => route('member.wallet.index', absolute: false),
            'withdrawal' => $this->withdrawal->reference,
            'amount_cents' => $this->withdrawal->amount_cents,
        ];
    }

    private function isPaid(): bool
    {
        return $this->withdrawal->status === WithdrawalRequest::PAID;
    }

    private function amount(): string
    {
        return '$'.number_format($this->withdrawal->amount_cents / 100, 2);
    }

    private function method(): string
    {
        return WithdrawalRequest::METHODS[$this->withdrawal->method] ?? $this->withdrawal->method;
    }
}
