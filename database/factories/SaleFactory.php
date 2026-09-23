<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Order;
use App\Models\Package;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->paid(),
            'member_id' => fn (array $attributes) => Order::query()->whereKey($attributes['order_id'])->firstOrFail()->member_id,
            'package_id' => fn (array $attributes) => Order::query()->whereKey($attributes['order_id'])->firstOrFail()->package_id,
            'amount' => fn (array $attributes) => Order::query()->whereKey($attributes['order_id'])->firstOrFail()->amount,
            'bv_value' => fn (array $attributes) => Package::query()->whereKey($attributes['package_id'])->firstOrFail()->bv_value,
            'status' => SaleStatus::Completed,
            'refunded_at' => null,
        ];
    }
}
