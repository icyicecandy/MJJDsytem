<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            if (! Schema::hasColumn('installations', 'reassignment_reason')) {
                $table->text('reassignment_reason')->nullable();
            }

            if (! Schema::hasColumn('installations', 'nap_code')) {
                $table->string('nap_code', 100)->nullable();
            }

            if (! Schema::hasColumn('installations', 'port_number')) {
                $table->string('port_number', 50)->nullable();
            }

            if (! Schema::hasColumn('installations', 'equipment_serial_number')) {
                $table->string('equipment_serial_number', 100)->nullable();
            }

            if (! Schema::hasColumn('installations', 'cable_length')) {
                $table->string('cable_length', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $columns = [
                'reassignment_reason',
                'equipment_serial_number',
                'cable_length',
            ];

            $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('installations', $column)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
