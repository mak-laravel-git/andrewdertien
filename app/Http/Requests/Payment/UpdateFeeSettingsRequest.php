<?php

namespace App\Http\Requests\Payment;

use App\Models\Pond;
use App\Support\Payments\FeeSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The platform fee and toss limits an admin sets in the dashboard. Money is entered in dollars.
 */
class UpdateFeeSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'platform_fee_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.FeeSettings::MAXIMUM_PERCENT],
            'minimum_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000'],
            'minimum_toss' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:'.Pond::MAXIMUM_AMOUNT_CENTS / 100],
            'minimum_withdrawal' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:100000'],
            'wallet_hold_days' => ['required', 'integer', 'min:0', 'max:90'],
            'adaptive_pricing' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform_fee_percent.max' => 'The platform fee can be at most '.FeeSettings::MAXIMUM_PERCENT.'%.',
            'platform_fee_percent.min' => 'The platform fee cannot be negative.',
            'minimum_toss.min' => 'The smallest toss must be at least $1.',
            'minimum_withdrawal.min' => 'The smallest withdrawal must be at least $1.',
            'wallet_hold_days.max' => 'Earnings can be held for at most 90 days.',
            'platform_fee_percent.decimal' => 'Use up to two decimal places, such as 12.5.',
            'minimum_fee.decimal' => 'Enter dollars and cents, such as 0.50.',
            'minimum_toss.decimal' => 'Enter dollars and cents, such as 5.00.',
        ];
    }
}
