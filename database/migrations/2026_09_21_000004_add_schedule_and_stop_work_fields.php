<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->time('technician_break_start')->nullable()->after('technician_shift_end');
            $table->time('technician_break_end')->nullable()->after('technician_break_start');
            $table->json('technician_unavailable_dates')->nullable()->after('technician_work_days');
            $table->unsignedSmallInteger('technician_max_active_tickets')->nullable()->after('technician_unavailable_dates');
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->string('cancellation_previous_status', 50)->nullable()->after('cancellation_reason');
        });
        Schema::table('installations', function (Blueprint $table) {
            $table->string('cancellation_previous_status', 50)->nullable()->after('status');
            $table->timestamp('stop_work_requested_at')->nullable()->after('cancellation_previous_status');
            $table->timestamp('stop_work_reviewed_at')->nullable()->after('stop_work_requested_at');
            $table->foreignId('stop_work_reviewed_by')->nullable()->after('stop_work_reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('stopped_at')->nullable()->after('stop_work_reviewed_by');
            $table->text('stop_work_technician_notes')->nullable()->after('stopped_at');
            $table->text('stop_work_admin_notes')->nullable()->after('stop_work_technician_notes');
        });
    }
    public function down(): void {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stop_work_reviewed_by');
            $table->dropColumn(['cancellation_previous_status','stop_work_requested_at','stop_work_reviewed_at','stopped_at','stop_work_technician_notes','stop_work_admin_notes']);
        });
        Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('cancellation_previous_status'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['technician_break_start','technician_break_end','technician_unavailable_dates','technician_max_active_tickets']));
    }
};
