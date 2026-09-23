<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named, row-locked counters. Values are handed out once and never reused.
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('next_value');
            $table->timestamps();
        });

        DB::table('sequences')->insert([
            'name' => 'member_code',
            'next_value' => 100001,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('members', function (Blueprint $table) {
            // Side the member asked for under their sponsor; the actual slot
            // (placement_parent_id/placement_side) is resolved on activation.
            $table->enum('preferred_side', ['left', 'right'])->nullable()->after('placement_side');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('preferred_side');
        });

        Schema::dropIfExists('sequences');
    }
};
