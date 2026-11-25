<?php

namespace App\Http\Requests\Member;

use App\Models\Pond;
use App\Models\User;
use App\Support\Payments\FeeSettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tossing a ball: how much, and optionally who it is for.
 */
class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['bail', 'nullable', 'numeric', 'decimal:0,2', 'min:'.$this->minimumTossCents() / 100, 'max:'.Pond::MAXIMUM_AMOUNT_CENTS / 100],
            'recipient_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNotNull('username'),
                Rule::notIn([$this->user()->id]),
                // A ball cannot be tossed to a member known to be under 18.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $recipient = User::query()->find($value);

                    if ($recipient && ! $recipient->canBeTossedABall()) {
                        $fail(__('A ball cannot be tossed to :name right now.', ['name' => $recipient->name]));
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'A ball can be any amount from $'.rtrim(rtrim(number_format($this->minimumTossCents() / 100, 2), '0'), '.').'.',
            'amount.max' => 'Please choose an amount of $'.number_format(Pond::MAXIMUM_AMOUNT_CENTS / 100).' or less.',
            'amount.numeric' => 'Please enter an amount in dollars.',
            'amount.decimal' => 'Please enter an amount in dollars and cents.',
            'recipient_id.not_in' => 'You can not toss a ball to yourself.',
            'recipient_id.exists' => 'That member could not be found.',
        ];
    }

    /**
     * The smallest amount an admin allows a ball to be tossed for.
     */
    private function minimumTossCents(): int
    {
        return app(FeeSettings::class)->minimumTossCents();
    }

    /**
     * The chosen amount in cents, or null to use the ball's suggested amount.
     */
    public function amountCents(): ?int
    {
        return $this->filled('amount') ? (int) round(((float) $this->input('amount')) * 100) : null;
    }
}
