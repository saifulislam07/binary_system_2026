<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 'simulator' = local click-through gateway, only usable outside production.
        DB::statement("ALTER TABLE payments MODIFY gateway ENUM('bkash','sslcommerz','nagad','simulator') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY gateway ENUM('bkash','sslcommerz','nagad') NOT NULL");
    }
};
