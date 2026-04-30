<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_call_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ivr_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ivr_import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_phone_number_id')->constrained()->cascadeOnDelete();
            $table->string('external_call_uuid')->unique();
            $table->timestamp('call_time')->nullable()->index();
            $table->string('call_direction')->nullable();
            $table->string('call_status')->nullable()->index();
            $table->string('customer_status')->nullable();
            $table->string('agent_status')->nullable();
            $table->unsignedInteger('total_duration_seconds')->default(0);
            $table->unsignedInteger('talk_time_seconds')->default(0);
            $table->string('call_action')->nullable();
            $table->json('dtmf_extensions')->nullable();
            $table->string('dtmf_outcome')->nullable()->index();
            $table->string('queue')->nullable();
            $table->string('disposition')->nullable();
            $table->string('sub_disposition')->nullable();
            $table->string('hangup_by')->nullable();
            $table->string('ivr_id')->nullable();
            $table->decimal('credits_deducted', 10, 2)->nullable();
            $table->timestamp('follow_up_date')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['client_phone_number_id', 'call_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_call_records');
    }
};
