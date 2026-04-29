<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'technician_employment_type')) {
                $column = $table->string('technician_employment_type', 30)->nullable();

                if (Schema::hasColumn('users', 'technician_shift')) {
                    $column->after('technician_shift');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'technician_employment_type')) {
                $table->dropColumn('technician_employment_type');
            }
        });
    }
};
