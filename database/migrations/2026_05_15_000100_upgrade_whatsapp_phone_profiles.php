<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_phone_profiles', function (Blueprint $table): void {
            $table->renameColumn('consecutive_failed_count', 'consecutive_hard_fail_count');
            $table->string('usage_status')->default('active')->after('last_messaged_at')->index();
            $table->timestamp('cooldown_until')->nullable()->after('usage_status');
            $table->text('last_failure_reason')->nullable()->after('last_message_status');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_phone_profiles', function (Blueprint $table): void {
            $table->renameColumn('consecutive_hard_fail_count', 'consecutive_failed_count');
            $table->dropColumn(['usage_status', 'cooldown_until', 'last_failure_reason']);
        });
    }
};
