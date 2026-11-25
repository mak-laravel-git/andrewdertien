<?php

namespace App\Http\Requests\Member;

use App\Models\WithdrawalRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A member asking for money in their wallet to be paid out, and where to send it.
 */
class StoreWithdrawalRequest extends FormRequest
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
            'amount' => ['bail', 'required', 'numeric', 'decimal:0,2', 'min:1', 'max:1000000'],
            'method' => ['required', Rule::in(array_keys(WithdrawalRequest::METHODS))],
            'details' => ['required', 'string', 'min:3', 'max:1000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Enter how much you would like to withdraw.',
            'amount.numeric' => 'Please enter an amount in dollars.',
            'amount.decimal' => 'Please enter an amount in dollars and cents.',
            'method.required' => 'Choose how you would like to be paid.',
            'details.required' => 'Tell us where to send the money.',
            'details.min' => 'Tell us where to send the money.',
        ];
    }

    /**
     * The amount asked for, in cents.
     */
    public function amountCents(): int
    {
        return (int) round(((float) $this->input('amount')) * 100);
    }
}
