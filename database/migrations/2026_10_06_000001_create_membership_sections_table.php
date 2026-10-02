<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-written text blocks of the public membership page, in both
        // languages. Rates, packages and ranks on that page come from their
        // own tables; the earnings disclaimer is fixed and not stored here.
        Schema::create('membership_sections', function (Blueprint $table) {
            $table->id();
            $table->string('title_en', 150);
            $table->string('title_bn', 150)->nullable();
            $table->text('body_en'); // sanitized HTML (App\Support\RichText)
            $table->text('body_bn')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_sections');
    }
};
