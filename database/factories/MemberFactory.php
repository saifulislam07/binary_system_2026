<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'member_code' => null,
            'sponsor_id' => null,
            'placement_parent_id' => null,
            'placement_side' => null,
            'package_id' => Package::factory(),
            'status' => MemberStatus::Pending,
            'nid' => fake()->numerify('##########'),
            'address' => fake()->address(),
            'activated_at' => null,
        ];
    }

    /**
     * An activated member with a member code (no tree placement).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MemberStatus::Active,
            'member_code' => 'MBR-'.fake()->unique()->numberBetween(500000, 999999),
            'activated_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MemberStatus::Suspended,
        ]);
    }
}
