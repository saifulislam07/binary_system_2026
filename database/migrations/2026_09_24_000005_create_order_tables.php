<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('member_id')->constrained();
            $table->foreignId('package_id')->constrained();
            $table->bigInteger('amount'); // poysha
            $table->enum('status', ['pending', 'paid', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at']);
            $table->index(['member_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('unit_price'); // poysha
            $table->bigInteger('total');      // poysha
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained();
            $table->foreignId('member_id')->constrained();
            $table->foreignId('package_id')->constrained();
            $table->bigInteger('amount');   // poysha (cash)
            $table->bigInteger('bv_value'); // centi-BV (what flows into team volume)
            $table->enum('status', ['completed', 'refunded'])->default('completed');
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['member_id', 'status']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained(); // a sale is refunded at most once
            $table->bigInteger('amount'); // poysha
            $table->text('reason');
            $table->foreignId('processed_by')->nullable()->constrained('admins');
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
