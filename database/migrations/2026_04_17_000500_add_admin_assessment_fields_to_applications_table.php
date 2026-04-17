<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'serviceability_status')) {
                $table->string('serviceability_status', 30)->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('applications', 'slot_availability_status')) {
                $table->string('slot_availability_status', 30)->nullable()->after('serviceability_status');
            }

            if (! Schema::hasColumn('applications', 'assessment_notes')) {
                $table->text('assessment_notes')->nullable()->after('slot_availability_status');
            }

            if (! Schema::hasColumn('applications', 'assessed_by')) {
                $table->foreignId('assessed_by')->nullable()->after('assessment_notes')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('applications', 'assessed_at')) {
                $table->timestamp('assessed_at')->nullable()->after('assessed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'assessed_by')) {
                $table->dropConstrainedForeignId('assessed_by');
            }

            $columns = [
                'serviceability_status',
                'slot_availability_status',
                'assessment_notes',
                'assessed_at',
            ];

            $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('applications', $column)));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
