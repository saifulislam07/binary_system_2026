<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-composed "Important Announcement" broadcasts (Phase 13).
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins');
            $table->string('title', 150);
            $table->text('body');
            // Segment: member status, optional minimum rank, optional package.
            $table->enum('audience', ['active', 'pending', 'suspended', 'all'])->default('active');
            $table->foreignId('min_rank_id')->nullable()->constrained('ranks');
            $table->foreignId('package_id')->nullable()->constrained('packages');
            // Extra channels besides in-app: subset of ["mail", "sms", "whatsapp"].
            $table->json('channels');
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
