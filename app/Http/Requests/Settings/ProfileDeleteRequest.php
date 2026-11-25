<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\WithdrawalRequest;
use App\Support\Wallet\Wallet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * An account with money in its wallet, or a withdrawal waiting to be paid, cannot be deleted:
     * the money would be lost. The member withdraws it first.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $hasMoney = app(Wallet::class)->summary($user)['total_cents'] !== 0;
            $hasRequest = WithdrawalRequest::query()->where('user_id', $user->id)->where('status', WithdrawalRequest::PENDING)->exists();

            if ($hasMoney || $hasRequest) {
                $validator->errors()->add('password', 'Your wallet still has money in it, or a withdrawal is waiting. Withdraw it and wait for it to be paid before deleting your account.');
            }
        }];
    }
}
