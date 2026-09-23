<?php

namespace Database\Factories;

use App\Enums\TransactionDirection;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => WalletTransactionType::Adjustment,
            'direction' => TransactionDirection::Credit,
            'amount' => fake()->numberBetween(1, 1_000) * 100,
            'balance_after' => null,
            'description' => fake()->sentence(),
            'status' => WalletTransactionStatus::Completed,
        ];
    }
}
