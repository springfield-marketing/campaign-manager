<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_phone_numbers', function (Blueprint $table): void {
            $table->string('label')->nullable()->after('national_number');
            $table->boolean('is_primary')->default(false)->after('is_uae')->index();
            $table->boolean('is_whatsapp')->default(false)->after('is_primary')->index();
            $table->string('verification_status')->default('unverified')->after('is_whatsapp')->index();
            $table->unsignedSmallInteger('priority')->default(100)->after('verification_status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('client_phone_numbers', function (Blueprint $table): void {
            $table->dropColumn([
                'label',
                'is_primary',
                'is_whatsapp',
                'verification_status',
                'priority',
            ]);
        });
    }
};
