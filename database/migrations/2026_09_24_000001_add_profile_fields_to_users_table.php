<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Normalized E.164 (+8801XXXXXXXXX). Uniqueness among active
            // members is enforced in Phase 3 (app) and Phase 12 (DB).
            $table->string('phone', 20)->nullable()->index()->after('email');
            $table->string('ip_registered', 45)->nullable()->after('password');
            $table->string('device_registered')->nullable()->after('ip_registered');
            $table->boolean('is_active')->default(true)->index()->after('device_registered');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['phone', 'ip_registered', 'device_registered', 'is_active']);
        });
    }
};
