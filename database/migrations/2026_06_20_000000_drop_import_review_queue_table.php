<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('import_review_queue');
    }

    public function down(): void
    {
        // The import review-queue feature was removed; no rollback.
    }
};
