<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Package',
            'description' => fake()->sentence(),
            'price' => $price = fake()->randomElement([100_000, 500_000, 1_000_000, 2_500_000]),
            'bv_value' => $price,
            'cost_of_goods' => intdiv($price * 40, 100),
            'is_qualifying' => true,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function nonQualifying(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_qualifying' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
