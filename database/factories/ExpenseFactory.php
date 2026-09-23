<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'amount' => fake()->numberBetween(100, 50_000) * 100,
            'description' => fake()->sentence(),
            'date' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'recorded_by' => null,
        ];
    }
}
