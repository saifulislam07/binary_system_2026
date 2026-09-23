<?php

namespace Database\Factories;

use App\Models\CommissionCycle;
use App\Models\Member;
use App\Models\TeamVolume;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamVolume>
 */
class TeamVolumeFactory extends Factory
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
            'commission_cycle_id' => CommissionCycle::factory(),
            'left_volume' => 0,
            'right_volume' => 0,
            'matched_volume' => 0,
            'carried_left' => 0,
            'carried_right' => 0,
        ];
    }
}
