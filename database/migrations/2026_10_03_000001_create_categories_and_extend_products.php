<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shop catalog: categories of products shown on the public shop.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_bn', 100)->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('slug')->nullable()->after('name');
            $table->string('brand', 100)->nullable()->after('slug');
            $table->text('description')->nullable()->after('sku');
            $table->text('highlights')->nullable()->after('description'); // one per line
            $table->bigInteger('compare_at_price')->nullable()->after('price'); // poysha; the "was" price
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->unsignedInteger('sort_order')->default(0)->after('is_featured');

            $table->index(['is_active', 'category_id']);
        });

        // Existing rows (none in a fresh install) get a slug from their SKU.
        DB::table('products')->whereNull('slug')->update(['slug' => DB::raw('LOWER(sku)')]);

        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'category_id']);
            $table->dropUnique(['slug']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['slug', 'brand', 'description', 'highlights', 'compare_at_price', 'is_featured', 'sort_order']);
        });

        Schema::dropIfExists('categories');
    }
};
