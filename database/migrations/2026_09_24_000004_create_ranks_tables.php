<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->unique();
            $table->bigInteger('min_personal_sales')->default(0); // poysha
            $table->bigInteger('min_team_sales')->default(0);     // poysha
            $table->unsignedInteger('min_active_team')->default(0);
            $table->bigInteger('bonus_amount')->default(0);       // poysha
            $table->timestamps();
        });

        Schema::create('rank_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained();
            $table->foreignId('rank_id')->constrained();
            $table->timestamp('achieved_at');
            $table->timestamps();

            $table->unique(['member_id', 'rank_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_achievements');
        Schema::dropIfExists('ranks');
    }
};
