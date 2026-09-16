<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->date('appointment_date')->nullable();
            $table->time('appointment_time')->nullable();
            $table->index(['technician_id', 'appointment_date', 'status'], 'tickets_technician_appointment_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_technician_appointment_index');
            $table->dropColumn(['appointment_date', 'appointment_time']);
        });
    }
};
