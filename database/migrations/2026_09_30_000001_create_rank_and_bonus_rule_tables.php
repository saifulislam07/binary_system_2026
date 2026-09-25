<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Highest rank achieved (ranks are never demoted). Null = base "Member".
            $table->foreignId('current_rank_id')->nullable()->after('package_id')->constrained('ranks');
        });

        // Threshold bonuses (rule #11 phase): leadership = active team size,
        // sales = personal sales. Each rule pays a member at most once.
        Schema::create('bonus_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['leadership', 'sales']);
            $table->string('name');
            $table->bigInteger('threshold'); // leadership: active members; sales: poysha of personal sales
            $table->bigInteger('amount');    // poysha
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::table('bonuses', function (Blueprint $table) {
            $table->foreignId('bonus_rule_id')->nullable()->after('member_id')->constrained();
            $table->foreignId('awarded_by')->nullable()->after('description')->constrained('admins');
            $table->unique(['member_id', 'bonus_rule_id']); // one payout per rule per member
        });
    }

    public function down(): void
    {
        Schema::table('bonuses', function (Blueprint $table) {
            $table->dropUnique(['member_id', 'bonus_rule_id']);
            $table->dropConstrainedForeignId('awarded_by');
            $table->dropConstrainedForeignId('bonus_rule_id');
        });

        Schema::dropIfExists('bonus_rules');

        Schema::table('members', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_rank_id'));
    }
};
