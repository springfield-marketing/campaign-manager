<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('external_campaign_id')->unique();
            $table->string('name')->nullable();
            $table->string('platform')->nullable();
            $table->string('state')->nullable();
            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedInteger('answered_calls')->default(0);
            $table->unsignedInteger('unanswered_calls')->default(0);
            $table->unsignedInteger('leads_count')->default(0);
            $table->unsignedInteger('more_info_count')->default(0);
            $table->unsignedInteger('unsubscribed_count')->default(0);
            $table->decimal('credits_used', 12, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_campaigns');
    }
};
