<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            if (! Schema::hasColumn('installations', 'reschedule_requested_at')) {
                $table->timestamp('reschedule_requested_at')->nullable()->after('reassignment_reason');
            }

            if (! Schema::hasColumn('installations', 'reschedule_preferred_date')) {
                $table->date('reschedule_preferred_date')->nullable()->after('reschedule_requested_at');
            }

            if (! Schema::hasColumn('installations', 'reschedule_preferred_time')) {
                $table->time('reschedule_preferred_time')->nullable()->after('reschedule_preferred_date');
            }

            if (! Schema::hasColumn('installations', 'reschedule_request_reason')) {
                $table->text('reschedule_request_reason')->nullable()->after('reschedule_preferred_time');
            }

            if (! Schema::hasColumn('installations', 'reschedule_reviewed_at')) {
                $table->timestamp('reschedule_reviewed_at')->nullable()->after('reschedule_request_reason');
            }

            if (! Schema::hasColumn('installations', 'reschedule_rejection_reason')) {
                $table->text('reschedule_rejection_reason')->nullable()->after('reschedule_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            foreach ([
                'reschedule_rejection_reason',
                'reschedule_reviewed_at',
                'reschedule_request_reason',
                'reschedule_preferred_time',
                'reschedule_preferred_date',
                'reschedule_requested_at',
            ] as $column) {
                if (Schema::hasColumn('installations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
