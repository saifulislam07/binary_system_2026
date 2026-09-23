<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'withdrawal_id' => null,
            'gateway' => PaymentGateway::Bkash,
            'gateway_ref' => fake()->unique()->uuid(),
            'amount' => 100_000,
            'status' => PaymentStatus::Initiated,
            'raw_response' => null,
        ];
    }
}
