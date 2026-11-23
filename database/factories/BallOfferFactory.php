<?php

namespace Database\Factories;

use App\Models\BallOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BallOffer>
 */
class BallOfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'message' => fake()->sentence(12),
        ];
    }
}
