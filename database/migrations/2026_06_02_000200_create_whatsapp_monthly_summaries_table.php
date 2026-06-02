<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_monthly_summaries', function (Blueprint $table): void {
            $table->id();
            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('month')->unsigned()->nullable();
            $table->unsignedInteger('total_messages')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_monthly_summaries');
    }
};
