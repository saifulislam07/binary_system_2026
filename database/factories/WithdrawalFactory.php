<?php

namespace Database\Factories;

use App\Enums\WithdrawalMethodType;
use App\Enums\WithdrawalStatus;
use App\Models\Member;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
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
            'amount' => 100_000,
            'method' => WithdrawalMethodType::MobileBanking,
            'account_details' => ['provider' => 'bkash', 'number' => '+8801'.fake()->numerify('#########')],
            'status' => WithdrawalStatus::Pending,
        ];
    }
}
