<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained();
            // Cached SUM(credits) - SUM(debits) of non-voided ledger rows (poysha).
            // Only WalletService writes this, in the same transaction as the ledger row.
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
        });

        // Append-only ledger. Never update amounts or delete rows; reverse with a new row.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained();
            $table->enum('type', [
                'referral_bonus', 'binary_commission', 'rank_bonus', 'sales_bonus',
                'leadership_bonus', 'performance_bonus', 'withdrawal', 'adjustment', 'refund', 'reversal',
            ]);
            $table->enum('direction', ['credit', 'debit']);
            $table->bigInteger('amount'); // poysha, always positive; direction gives the sign
            $table->bigInteger('balance_after')->nullable();
            $table->nullableMorphs('reference');
            $table->string('description')->nullable();
            $table->enum('status', ['pending', 'completed', 'voided'])->default('completed');
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['wallet_id', 'type', 'status']);
        });

        Schema::create('withdrawal_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained();
            $table->enum('type', ['bank', 'mobile_banking']);
            $table->json('details'); // bank: account_name/number, bank, branch; mobile: provider, number
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['member_id', 'is_default']);
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained();
            $table->bigInteger('amount'); // poysha
            $table->enum('method', ['bank', 'mobile_banking']);
            $table->json('account_details'); // snapshot at request time
            $table->enum('status', ['pending', 'approved', 'processing', 'paid', 'rejected'])->default('pending');
            $table->foreignId('admin_id')->nullable()->constrained('admins');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['member_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained();
            $table->foreignId('withdrawal_id')->nullable()->constrained();
            $table->enum('gateway', ['bkash', 'sslcommerz', 'nagad']);
            $table->string('gateway_ref')->nullable();
            $table->bigInteger('amount'); // poysha
            $table->enum('status', ['initiated', 'success', 'failed', 'cancelled'])->default('initiated');
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_ref']); // idempotent callbacks
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('withdrawal_methods');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
