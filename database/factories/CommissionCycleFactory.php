<?php

namespace Database\Factories;

use App\Enums\CommissionCycleStatus;
use App\Models\CommissionCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionCycle>
 */
class CommissionCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cycle_date' => fake()->unique()->dateTimeBetween('-2 years')->format('Y-m-d'),
            'status' => CommissionCycleStatus::Running,
            'closed_at' => null,
        ];
    }
}
