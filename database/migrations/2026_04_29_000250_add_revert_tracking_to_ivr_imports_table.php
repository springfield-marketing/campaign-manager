<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ivr_imports', function (Blueprint $table): void {
            $table->timestamp('reverted_at')->nullable()->after('completed_at');
            $table->foreignId('reverted_by')->nullable()->after('reverted_at')->constrained('users')->nullOnDelete();
            $table->text('revert_reason')->nullable()->after('reverted_by');

            $table->index(['type', 'original_file_name', 'reverted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ivr_imports', function (Blueprint $table): void {
            $table->dropIndex(['type', 'original_file_name', 'reverted_at']);
            $table->dropConstrainedForeignId('reverted_by');
            $table->dropColumn(['reverted_at', 'revert_reason']);
        });
    }
};
