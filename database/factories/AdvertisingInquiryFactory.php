<?php

namespace Database\Factories;

use App\Models\AdvertisingInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvertisingInquiry>
 */
class AdvertisingInquiryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'company' => fake()->company(),
            'website' => fake()->url(),
            'message' => fake()->sentence(14),
        ];
    }
}
