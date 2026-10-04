<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('assigned_staff_id')
                ->nullable()
                ->after('technician_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('assignment_reason')->nullable()->after('assignment_source');
            $table->foreignId('assignment_changed_by')
                ->nullable()
                ->after('assignment_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('assignment_unavailable_since')->nullable()->after('assignment_changed_by');
            $table->index(['assigned_staff_id', 'status'], 'tickets_staff_status_index');
        });

        Schema::create('ticket_assignment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 40);
            $table->text('reason');
            $table->timestamps();
        });

        DB::table('tickets')
            ->whereNotNull('technician_id')
            ->update(['assigned_staff_id' => DB::raw('technician_id')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignment_events');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_staff_status_index');
            $table->dropConstrainedForeignId('assigned_staff_id');
            $table->dropConstrainedForeignId('assignment_changed_by');
            $table->dropColumn(['assignment_reason', 'assignment_unavailable_since']);
        });
    }
};
