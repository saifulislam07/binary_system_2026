<?php

namespace Database\Factories;

use App\Models\IncomeTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeTransaction>
 */
class IncomeTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'other',
            'amount' => fake()->numberBetween(100, 50_000) * 100,
            'description' => fake()->sentence(),
            'date' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'recorded_by' => null,
        ];
    }
}
