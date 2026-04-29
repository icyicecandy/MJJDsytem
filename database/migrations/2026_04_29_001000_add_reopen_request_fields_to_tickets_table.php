<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'reopen_requested_at')) {
                $table->timestamp('reopen_requested_at')->nullable()->after('photo_path');
            }

            if (! Schema::hasColumn('tickets', 'reopen_reason')) {
                $table->text('reopen_reason')->nullable()->after('reopen_requested_at');
            }

            if (! Schema::hasColumn('tickets', 'reopen_reviewed_at')) {
                $table->timestamp('reopen_reviewed_at')->nullable()->after('reopen_reason');
            }

            if (! Schema::hasColumn('tickets', 'reopen_reviewed_by')) {
                $table->foreignId('reopen_reviewed_by')
                    ->nullable()
                    ->after('reopen_reviewed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'reopen_reviewed_by')) {
                $table->dropConstrainedForeignId('reopen_reviewed_by');
            }

            foreach (['reopen_reviewed_at', 'reopen_reason', 'reopen_requested_at'] as $column) {
                if (Schema::hasColumn('tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
