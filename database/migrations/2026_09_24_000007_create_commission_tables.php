<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-editable key/value config: rates (basis points), caps (poysha), flags.
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_cycles', function (Blueprint $table) {
            $table->id();
            $table->date('cycle_date')->unique();
            $table->enum('status', ['running', 'closed'])->default('running');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('team_volumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained();
            $table->foreignId('commission_cycle_id')->constrained();
            // All centi-BV. left/right = volume available at matching time.
            $table->bigInteger('left_volume')->default(0);
            $table->bigInteger('right_volume')->default(0);
            $table->bigInteger('matched_volume')->default(0);
            $table->bigInteger('carried_left')->default(0);
            $table->bigInteger('carried_right')->default(0);
            $table->timestamps();

            $table->unique(['member_id', 'commission_cycle_id']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained();
            $table->enum('type', ['referral', 'binary', 'rank', 'leadership', 'sales', 'performance']);
            $table->foreignId('source_sale_id')->nullable()->constrained('sales');
            $table->foreignId('commission_cycle_id')->nullable()->constrained();
            $table->bigInteger('amount'); // poysha
            $table->date('cycle_date');
            $table->enum('status', ['pending', 'paid', 'voided', 'reversed'])->default('pending');
            $table->string('description')->nullable();
            $table->timestamps();

            // Cap checks sum a member's commissions in a date window.
            $table->index(['member_id', 'type', 'cycle_date']);
            $table->index(['member_id', 'status', 'cycle_date']);
            $table->index(['type', 'status', 'cycle_date']);
        });

        Schema::create('bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained();
            $table->enum('type', ['sponsor', 'binary', 'rank', 'leadership', 'sales', 'performance']);
            $table->bigInteger('amount'); // poysha
            $table->date('cycle_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'voided', 'reversed'])->default('pending');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'type', 'cycle_date']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonuses');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('team_volumes');
        Schema::dropIfExists('commission_cycles');
        Schema::dropIfExists('commission_rules');
    }
};
