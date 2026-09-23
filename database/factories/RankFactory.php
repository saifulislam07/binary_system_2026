<?php

namespace Database\Factories;

use App\Models\Rank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rank>
 */
class RankFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'sort_order' => fake()->unique()->numberBetween(100, 10_000),
            'min_personal_sales' => 0,
            'min_team_sales' => 0,
            'min_active_team' => 0,
            'bonus_amount' => 0,
        ];
    }
}
