<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rule #12 at the database level: a mobile number or NID may belong to
        // only ONE active member. The phone is copied from users so one table
        // holds both the value and the status; the generated columns are NULL
        // unless the member is active, so pending/suspended rows never collide.
        Schema::table('members', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('nid');
        });

        DB::statement('UPDATE members m JOIN users u ON u.id = m.user_id SET m.phone = u.phone');

        DB::statement("ALTER TABLE members
            ADD COLUMN active_phone VARCHAR(20) GENERATED ALWAYS AS (IF(status = 'active', phone, NULL)) STORED,
            ADD COLUMN active_nid VARCHAR(30) GENERATED ALWAYS AS (IF(status = 'active', nid, NULL)) STORED,
            ADD UNIQUE INDEX members_active_phone_unique (active_phone),
            ADD UNIQUE INDEX members_active_nid_unique (active_nid)");

        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->enum('guard', ['web', 'admin']);
            $table->unsignedBigInteger('authenticatable_id')->nullable(); // null on a failed login for an unknown email
            $table->string('email')->nullable();
            $table->enum('event', ['login', 'failed', 'registered']);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->char('device_hash', 64)->nullable(); // sha256 of the user agent
            $table->boolean('new_device')->default(false);
            $table->boolean('new_ip')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['guard', 'authenticatable_id', 'created_at']);
            $table->index(['ip', 'created_at']);
        });

        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained();
            $table->string('type', 50); // rapid_withdrawal, withdrawal_exceeds_earnings, activation_blocked_duplicate
            $table->nullableMorphs('subject');
            $table->json('details');
            $table->enum('status', ['open', 'reviewed', 'dismissed'])->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('admins');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            // The scanner never raises the same flag twice for the same thing.
            $table->unique(['member_id', 'type', 'subject_type', 'subject_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_flags');
        Schema::dropIfExists('login_history');

        DB::statement('ALTER TABLE members
            DROP INDEX members_active_phone_unique,
            DROP INDEX members_active_nid_unique,
            DROP COLUMN active_phone,
            DROP COLUMN active_nid');

        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('phone'));
    }
};
