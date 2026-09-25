<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // transferred: volume moved off an old upline by an admin placement adjustment.
        DB::statement("ALTER TABLE volume_consumptions MODIFY kind ENUM('matched','flushed','refunded','restored','dissolved','transferred') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE volume_consumptions MODIFY kind ENUM('matched','flushed','refunded','restored','dissolved') NOT NULL");
    }
};
