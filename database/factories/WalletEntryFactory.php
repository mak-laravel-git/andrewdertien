<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletEntry>
 */
class WalletEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => WalletEntry::ADJUSTMENT,
            'amount_cents' => 1000,
            'currency' => 'usd',
            'available_at' => now(),
            'description' => fake()->sentence(4),
        ];
    }
}
