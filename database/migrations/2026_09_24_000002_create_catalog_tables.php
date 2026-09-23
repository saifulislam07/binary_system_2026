<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->bigInteger('price');                     // poysha
            $table->bigInteger('bv_value');                  // centi-BV
            $table->bigInteger('cost_of_goods')->default(0); // poysha, for profit reports
            $table->boolean('is_qualifying')->default(true); // pays referral bonus
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->bigInteger('price'); // poysha
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('package_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);

            $table->unique(['package_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('packages');
    }
};
