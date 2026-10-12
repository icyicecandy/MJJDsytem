<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_unavailability_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('requested_start_at');
            $table->dateTime('requested_end_at');
            $table->string('category', 50);
            $table->text('reason');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_original_name')->nullable();
            $table->string('status', 30)->default('pending');
            $table->dateTime('approved_start_at')->nullable();
            $table->dateTime('approved_end_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['technician_id', 'status'], 'tech_unavailability_technician_status_idx');
            $table->index(['requested_start_at', 'requested_end_at'], 'tech_unavailability_requested_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_unavailability_requests');
    }
};
