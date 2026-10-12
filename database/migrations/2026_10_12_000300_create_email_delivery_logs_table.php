<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notification_type', 120)->index();
            $table->string('recipient', 190)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->string('retry_status', 20)->default('not_needed');
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_delivery_logs');
    }
};
