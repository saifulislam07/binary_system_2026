<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Refund;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'amount' => fn (array $attributes) => Sale::query()->whereKey($attributes['sale_id'])->firstOrFail()->amount,
            'reason' => fake()->sentence(),
            'processed_by' => Admin::factory(),
            'processed_at' => now(),
        ];
    }
}
