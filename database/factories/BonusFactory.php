<?php

namespace Database\Factories;

use App\Enums\BonusType;
use App\Enums\PayoutStatus;
use App\Models\Bonus;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bonus>
 */
class BonusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'type' => BonusType::Sponsor,
            'amount' => fake()->numberBetween(1, 5_000) * 100,
            'cycle_date' => today(),
            'status' => PayoutStatus::Pending,
            'description' => fake()->sentence(),
        ];
    }
}
