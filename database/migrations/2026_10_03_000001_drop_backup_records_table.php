<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('backup_records');
    }

    public function down(): void
    {
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('frequency', 30)->default('manual');
            $table->string('status', 30)->default('pending');
            $table->string('disk', 80)->default('backup');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->string('offsite_disk', 80)->nullable();
            $table->string('offsite_path')->nullable();
            $table->string('offsite_status', 30)->default('not_configured');
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->char('recovery_token_hash', 64)->nullable();
            $table->timestamp('recovery_token_expires_at')->nullable();
            $table->foreignId('recovery_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recovery_authorized_at')->nullable();
            $table->timestamp('last_restored_at')->nullable();
            $table->foreignId('last_restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'completed_at']);
            $table->index(['frequency', 'completed_at']);
        });
    }
};
