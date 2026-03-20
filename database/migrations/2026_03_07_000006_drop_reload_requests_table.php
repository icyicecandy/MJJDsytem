<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reload_requests')) {
            Schema::dropIfExists('reload_requests');
        }
    }

    public function down(): void
    {
        Schema::create('reload_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('payment_reference');
            $table->decimal('amount', 8, 2);
            $table->string('receipt_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
};
