<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            if (! Schema::hasColumn('installations', 'nap_code')) {
                $table->string('nap_code')->nullable()->after('nap_photo_path');
            }
            if (! Schema::hasColumn('installations', 'lp_code')) {
                $table->string('lp_code')->nullable()->after('nap_code');
            }
            if (! Schema::hasColumn('installations', 'np_code')) {
                $table->string('np_code')->nullable()->after('lp_code');
            }
            if (! Schema::hasColumn('installations', 'full_nap_label')) {
                $table->string('full_nap_label')->nullable()->after('np_code');
            }
            if (! Schema::hasColumn('installations', 'port_number')) {
                $table->string('port_number')->nullable()->after('full_nap_label');
            }
            if (! Schema::hasColumn('installations', 'jo_number')) {
                $table->string('jo_number')->nullable()->after('port_number');
            }
            if (! Schema::hasColumn('installations', 'activation_date')) {
                $table->date('activation_date')->nullable()->after('jo_number');
            }
            if (! Schema::hasColumn('installations', 'activation_status')) {
                $table->string('activation_status')->nullable()->after('activation_date');
            }
            if (! Schema::hasColumn('installations', 'package_name')) {
                $table->string('package_name')->nullable()->after('activation_status');
            }
            if (! Schema::hasColumn('installations', 'technician_remarks')) {
                $table->text('technician_remarks')->nullable()->after('package_name');
            }
            if (! Schema::hasColumn('installations', 'router_serial')) {
                $table->string('router_serial')->nullable()->after('technician_remarks');
            }
            if (! Schema::hasColumn('installations', 'cable_reel_no')) {
                $table->string('cable_reel_no')->nullable()->after('router_serial');
            }
            if (! Schema::hasColumn('installations', 'material_notes')) {
                $table->text('material_notes')->nullable()->after('cable_reel_no');
            }
            if (! Schema::hasColumn('installations', 'speed_test_result')) {
                $table->string('speed_test_result')->nullable()->after('material_notes');
            }
            if (! Schema::hasColumn('installations', 'router_photo_path')) {
                $table->string('router_photo_path')->nullable()->after('speed_test_result');
            }
            if (! Schema::hasColumn('installations', 'acceptance_report_photo_path')) {
                $table->string('acceptance_report_photo_path')->nullable()->after('router_photo_path');
            }
            if (! Schema::hasColumn('installations', 'cable_reel_photo_path')) {
                $table->string('cable_reel_photo_path')->nullable()->after('acceptance_report_photo_path');
            }
            if (! Schema::hasColumn('installations', 'customer_signature_photo_path')) {
                $table->string('customer_signature_photo_path')->nullable()->after('cable_reel_photo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $columns = [
                'nap_code',
                'lp_code',
                'np_code',
                'full_nap_label',
                'port_number',
                'jo_number',
                'activation_date',
                'activation_status',
                'package_name',
                'technician_remarks',
                'router_serial',
                'cable_reel_no',
                'material_notes',
                'speed_test_result',
                'router_photo_path',
                'acceptance_report_photo_path',
                'cable_reel_photo_path',
                'customer_signature_photo_path',
            ];

            $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('installations', $column)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
