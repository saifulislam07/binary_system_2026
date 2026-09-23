<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'package_id' => null,
            'product_id' => Product::factory(),
            'quantity' => 1,
            'unit_price' => 50_000,
            'total' => fn (array $attributes) => $attributes['unit_price'] * $attributes['quantity'],
        ];
    }
}
