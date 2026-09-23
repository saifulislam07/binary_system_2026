<?php

namespace Database\Factories;

use App\Enums\CommissionType;
use App\Enums\PayoutStatus;
use App\Models\Commission;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commission>
 */
class CommissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'type' => CommissionType::Binary,
            'source_sale_id' => null,
            'commission_cycle_id' => null,
            'amount' => fake()->numberBetween(1, 5_000) * 100,
            'cycle_date' => today(),
            'status' => PayoutStatus::Pending,
        ];
    }
}
