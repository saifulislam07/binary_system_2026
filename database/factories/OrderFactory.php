<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Member;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.Str::upper(Str::random(12)),
            'member_id' => Member::factory(),
            'package_id' => Package::factory(),
            'amount' => fn (array $attributes) => Package::query()->whereKey($attributes['package_id'])->value('price') ?? 100_000,
            'status' => OrderStatus::Pending,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
