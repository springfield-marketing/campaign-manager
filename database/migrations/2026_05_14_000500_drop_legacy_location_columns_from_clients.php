<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('clients', 'clients_city_index')) {
            Schema::table('clients', function (Blueprint $table): void {
                $table->dropIndex('clients_city_index');
            });
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn(['country', 'city', 'community']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('community')->nullable();
        });
    }
};
