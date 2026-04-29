<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'disabled_at')) {
                $table->timestamp('disabled_at')->nullable()->after('deletion_requested_at');
            }

            if (! Schema::hasColumn('users', 'disabled_by')) {
                $table->foreignId('disabled_by')->nullable()->after('disabled_at')->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('users', 'technician_shift')) {
            DB::table('users')
                ->where('role', 'technician')
                ->update(['technician_shift' => User::TECHNICIAN_STANDARD_SHIFT]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'disabled_by')) {
                $table->dropConstrainedForeignId('disabled_by');
            }

            if (Schema::hasColumn('users', 'disabled_at')) {
                $table->dropColumn('disabled_at');
            }
        });
    }
};
