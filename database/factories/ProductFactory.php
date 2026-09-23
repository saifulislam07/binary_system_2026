<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->colorName()).' '.fake()->word(),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'price' => fake()->numberBetween(100, 5_000) * 100,
            'is_active' => true,
        ];
    }
}
