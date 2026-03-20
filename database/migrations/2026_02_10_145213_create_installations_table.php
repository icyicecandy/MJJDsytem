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
    Schema::create('installations', function (Blueprint $table) {
        $table->id();

        // Link to application
        $table->foreignId('application_id')
              ->constrained()
              ->onDelete('cascade');

        // Assigned technician
        $table->foreignId('technician_id')
              ->constrained()
              ->onDelete('cascade');

        // Schedule
        $table->date('installation_date');
        $table->time('installation_time')->nullable();

        // Status of installation
        $table->string('status')->default('scheduled');
        // scheduled | completed | cancelled

        $table->timestamps();
    });
}
};
