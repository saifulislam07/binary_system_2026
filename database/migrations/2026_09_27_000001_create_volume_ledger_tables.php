<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One lot per (sale, upline ancestor): the sale's BV as it sits on that
        // ancestor's left or right side. `remaining` is the unmatched part.
        // Invariant: binary_nodes.{side}_volume = SUM(remaining) of that member's lots on that side.
        Schema::create('volume_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained(); // the ancestor who receives the volume
            $table->enum('side', ['left', 'right']);
            $table->foreignId('sale_id')->constrained();
            $table->bigInteger('bv');        // centi-BV accrued
            $table->bigInteger('remaining'); // centi-BV not yet matched/flushed/refunded
            $table->timestamps();

            // FIFO scans: oldest open lots of a member's side.
            $table->index(['member_id', 'side', 'remaining', 'id']);
            $table->index('sale_id');
        });

        // Every movement of a lot's `remaining`, so a refund can be traced
        // back to exactly which cycle matched it and against what.
        Schema::create('volume_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volume_lot_id')->constrained();
            $table->foreignId('team_volume_id')->nullable()->constrained();
            // matched/flushed: consumed by a cycle. refunded: removed by a refund.
            // restored: pairing undone by a refund and the volume given back to this lot.
            // dissolved: pairing undone by a refund, volume NOT given back (it would have
            // been flushed anyway because carry-forward was off in that cycle).
            $table->enum('kind', ['matched', 'flushed', 'refunded', 'restored', 'dissolved']);
            $table->bigInteger('bv');
            $table->foreignId('restores_consumption_id')->nullable()->constrained('volume_consumptions');
            $table->timestamps();

            $table->index(['volume_lot_id', 'kind']);
            $table->index(['team_volume_id', 'kind']);
        });

        Schema::table('team_volumes', function (Blueprint $table) {
            $table->bigInteger('flushed_left')->default(0)->after('carried_right');
            $table->bigInteger('flushed_right')->default(0)->after('flushed_left');
            $table->boolean('carry_forward_enabled')->default(true)->after('flushed_right');
            // Commission for this cycle's own matching (poysha).
            $table->bigInteger('gross_commission')->default(0)->after('carry_forward_enabled');
            $table->bigInteger('paid_commission')->default(0)->after('gross_commission');
            $table->bigInteger('overflow_commission')->default(0)->after('paid_commission');
            $table->enum('overflow_action', ['void', 'carry_forward'])->nullable()->after('overflow_commission');
            // Previously carried-forward commission paid out in this cycle.
            $table->bigInteger('deferred_released')->default(0)->after('overflow_action');
        });

        Schema::table('binary_nodes', function (Blueprint $table) {
            // Cap overflow waiting to be paid in a later cycle (poysha).
            $table->bigInteger('deferred_commission')->default(0)->after('right_lifetime_volume');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->foreignId('team_volume_id')->nullable()->after('commission_cycle_id')->constrained();
            // Reversal rows carry a negative amount and point at what they reverse.
            $table->foreignId('reverses_commission_id')->nullable()->after('team_volume_id')->constrained('commissions');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('reversed_at'));

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverses_commission_id');
            $table->dropConstrainedForeignId('team_volume_id');
        });

        Schema::table('binary_nodes', fn (Blueprint $table) => $table->dropColumn('deferred_commission'));

        Schema::table('team_volumes', function (Blueprint $table) {
            $table->dropColumn([
                'flushed_left', 'flushed_right', 'carry_forward_enabled', 'gross_commission',
                'paid_commission', 'overflow_commission', 'overflow_action', 'deferred_released',
            ]);
        });

        Schema::dropIfExists('volume_consumptions');
        Schema::dropIfExists('volume_lots');
    }
};
