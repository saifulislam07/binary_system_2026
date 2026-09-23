<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RankAchievement>
 */
class RankAchievementFactory extends Factory
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
            'rank_id' => Rank::factory(),
            'achieved_at' => now(),
        ];
    }
}
