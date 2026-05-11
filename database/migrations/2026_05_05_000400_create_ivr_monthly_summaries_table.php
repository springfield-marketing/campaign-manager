<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_monthly_summaries', function (Blueprint $table): void {
            $table->id();
            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('month')->unsigned()->nullable();
            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedInteger('answered_calls')->default(0);
            $table->unsignedInteger('missed_calls')->default(0);
            $table->unsignedInteger('leads')->default(0);
            $table->unsignedInteger('more_info')->default(0);
            $table->unsignedInteger('unsubscribed')->default(0);
            $table->unsignedInteger('minutes_consumed')->default(0);
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_monthly_summaries');
    }
};
