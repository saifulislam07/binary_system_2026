<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            // Assigned on activation (MBR-100001…); null while pending.
            $table->string('member_code', 20)->nullable()->unique();
            $table->foreignId('sponsor_id')->nullable()->constrained('members');
            $table->foreignId('placement_parent_id')->nullable()->constrained('members');
            $table->enum('placement_side', ['left', 'right'])->nullable();
            $table->foreignId('package_id')->nullable()->constrained();
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->string('nid', 30)->nullable()->index();
            $table->text('address')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'activated_at']);
            // One member per slot under a parent.
            $table->unique(['placement_parent_id', 'placement_side']);
        });

        // Denormalized tree + volume state, kept in sync with members.placement_*.
        Schema::create('binary_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained();
            $table->foreignId('left_child_id')->nullable()->unique()->constrained('members');
            $table->foreignId('right_child_id')->nullable()->unique()->constrained('members');
            // Current-cycle unmatched volume (centi-BV); consumed by matching.
            $table->bigInteger('left_volume')->default(0);
            $table->bigInteger('right_volume')->default(0);
            // Volume carried into the current cycle from the previous one.
            $table->bigInteger('left_volume_carry')->default(0);
            $table->bigInteger('right_volume_carry')->default(0);
            // Lifetime team sales per side (never consumed; for ranks/dashboards).
            $table->bigInteger('left_lifetime_volume')->default(0);
            $table->bigInteger('right_lifetime_volume')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binary_nodes');
        Schema::dropIfExists('members');
    }
};
