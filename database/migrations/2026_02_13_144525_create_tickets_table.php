<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            // Who created the ticket (customer)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Optional technician assignment
            $table->foreignId('technician_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('subject');
            $table->text('description');

            // Status: open, in_progress, resolved
            $table->string('status')->default('open');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
