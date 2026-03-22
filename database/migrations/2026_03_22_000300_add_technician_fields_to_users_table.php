<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'technician_shift')) {
                $table->string('technician_shift', 100)->nullable()->after('address');
            }

            if (! Schema::hasColumn('users', 'technician_status')) {
                $table->string('technician_status', 50)->default('available')->after('technician_shift');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'technician_status')) {
                $table->dropColumn('technician_status');
            }

            if (Schema::hasColumn('users', 'technician_shift')) {
                $table->dropColumn('technician_shift');
            }
        });
    }
};
