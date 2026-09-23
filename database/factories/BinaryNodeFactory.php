<?php

namespace Database\Factories;

use App\Models\BinaryNode;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BinaryNode>
 */
class BinaryNodeFactory extends Factory
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
            'left_child_id' => null,
            'right_child_id' => null,
            'left_volume' => 0,
            'right_volume' => 0,
            'left_volume_carry' => 0,
            'right_volume_carry' => 0,
            'left_lifetime_volume' => 0,
            'right_lifetime_volume' => 0,
        ];
    }
}
