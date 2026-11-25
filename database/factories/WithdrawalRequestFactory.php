<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithdrawalRequest>
 */
class WithdrawalRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount_cents' => 2500,
            'currency' => 'usd',
            'method' => 'paypal',
            'details' => fake()->safeEmail(),
            'status' => WithdrawalRequest::PENDING,
        ];
    }
}
