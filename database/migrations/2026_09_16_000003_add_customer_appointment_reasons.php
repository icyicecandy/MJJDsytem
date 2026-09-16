<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->text('customer_reschedule_reason')->nullable();
            $table->timestamp('customer_rescheduled_at')->nullable();
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->text('appointment_change_reason')->nullable();
            $table->timestamp('appointment_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('installations', fn (Blueprint $table) => $table->dropColumn(['customer_reschedule_reason', 'customer_rescheduled_at']));
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn(['appointment_change_reason', 'appointment_changed_at']));
    }
};
