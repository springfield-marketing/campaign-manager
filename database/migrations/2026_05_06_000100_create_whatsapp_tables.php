<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('original_file_name');
            $table->string('stored_file_name')->nullable();
            $table->string('storage_path')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->unsignedBigInteger('reverted_by')->nullable();
            $table->text('revert_reason')->nullable();
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reverted_by')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
            $table->index('type');
        });

        Schema::create('whatsapp_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('total_messages')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('unsubscribed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_phone_number_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('template_name')->nullable();
            $table->string('delivery_status')->nullable();
            $table->text('failure_reason')->nullable();
            $table->boolean('has_quick_replies')->default(false);
            $table->string('quick_reply_1')->nullable();
            $table->string('quick_reply_2')->nullable();
            $table->string('quick_reply_3')->nullable();
            $table->boolean('clicked')->default(false);
            $table->boolean('retried')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('whatsapp_campaign_id');
            $table->index('whatsapp_import_id');
            $table->index('delivery_status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_campaigns');
        Schema::dropIfExists('whatsapp_imports');
    }
};
