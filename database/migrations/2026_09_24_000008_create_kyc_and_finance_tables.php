<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained();
            $table->enum('type', ['nid', 'passport']);
            $table->string('document_number', 50);
            $table->string('file_path')->nullable(); // files themselves live in MediaLibrary
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('admins');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'document_number']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->enum('category', [
                'product_cost', 'commission', 'delivery', 'marketing',
                'salary', 'server', 'gateway_fees', 'other',
            ]);
            $table->bigInteger('amount'); // poysha
            $table->string('description')->nullable();
            $table->date('date');
            $table->foreignId('recorded_by')->nullable()->constrained('admins');
            $table->timestamps();

            $table->index(['date', 'category']);
        });

        Schema::create('income_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50); // product_sales, other, …
            $table->bigInteger('amount'); // poysha
            $table->string('description')->nullable();
            $table->date('date');
            $table->foreignId('recorded_by')->nullable()->constrained('admins');
            $table->timestamps();

            $table->index(['date', 'source']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('income_transactions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('kyc_documents');
    }
};
