<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_phone_numbers', function (Blueprint $table) {
            $table->dropIndex('client_phone_numbers_usage_status_index');
            $table->dropColumn(['usage_status', 'last_call_outcome', 'last_called_at', 'cooldown_until']);
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE ivr_phone_profiles ADD CONSTRAINT ivr_phone_profiles_usage_status_check
                CHECK (usage_status IN ('active', 'inactive', 'dead'))");
            DB::statement('ALTER TABLE client_phone_numbers DROP CONSTRAINT client_phone_numbers_usage_status_check');
        }
    }

    public function down(): void
    {
        Schema::table('client_phone_numbers', function (Blueprint $table) {
            $table->string('usage_status')->default('active')->after('is_primary');
            $table->string('last_call_outcome')->nullable()->after('usage_status');
            $table->timestamp('last_called_at')->nullable()->after('last_call_outcome');
            $table->timestamp('cooldown_until')->nullable()->after('last_called_at');
        });
    }
};
