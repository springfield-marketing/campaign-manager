<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_aliases', function (Blueprint $table): void {
            $table->id();
            // official_area | marketing_area | project | building
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('alias_name');
            $table->string('source')->nullable();
            $table->string('confidence_level')->nullable()->comment('high|medium|low');
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'alias_name']);
            $table->index(['entity_type', 'entity_id']);
            // GIN index for case-insensitive lookups added after table creation
        });

        // Lower-cased alias for fast fuzzy lookup without full-text search
        DB::statement("CREATE INDEX place_aliases_alias_lower_idx ON place_aliases (lower(alias_name))");
    }

    public function down(): void
    {
        Schema::dropIfExists('place_aliases');
    }
};
