<?php

namespace Database\Factories;

use App\Enums\WithdrawalMethodType;
use App\Models\Member;
use App\Models\WithdrawalMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithdrawalMethod>
 */
class WithdrawalMethodFactory extends Factory
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
            'type' => WithdrawalMethodType::MobileBanking,
            'details' => ['provider' => 'bkash', 'number' => '+8801'.fake()->numerify('#########')],
            'is_default' => true,
        ];
    }
}
