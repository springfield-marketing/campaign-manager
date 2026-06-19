<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Symmetric channel-membership flag for IVR, mirroring is_whatsapp. Set only when a number
 * appears in an IVR campaign result (CampaignResultsProcessor) — channel membership is earned by
 * real campaign activity, never stamped at raw-contact import time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_phone_numbers', function (Blueprint $table): void {
            $table->boolean('is_ivr')->default(false)->after('is_whatsapp')->index();
        });
    }

    public function down(): void
    {
        Schema::table('client_phone_numbers', function (Blueprint $table): void {
            $table->dropColumn('is_ivr');
        });
    }
};
