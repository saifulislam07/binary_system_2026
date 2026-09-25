<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            // The pending wallet debit that holds the funds while the request is open.
            $table->foreignId('wallet_transaction_id')->nullable()->after('account_details')->constrained();
            // Bank / bKash / Nagad transaction id the admin paid out with.
            $table->string('payout_reference', 100)->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_transaction_id');
            $table->dropColumn('payout_reference');
        });
    }
};
