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
            if (! Schema::hasColumn('users', 'customer_id')) {
                $table->string('customer_id', 30)->nullable()->unique()->after('email');
            }

            if (! Schema::hasColumn('users', 'technician_id')) {
                $table->string('technician_id', 30)->nullable()->unique()->after('customer_id');
            }
        });

        if (Schema::hasColumn('users', 'customer_id')) {
            DB::table('users')
                ->where('role', 'customer')
                ->whereNull('customer_id')
                ->select('id')
                ->orderBy('id')
                ->get()
                ->each(function ($user): void {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['customer_id' => User::formatCustomerId((int) $user->id)]);
                });
        }

        if (Schema::hasColumn('users', 'technician_id')) {
            DB::table('users')
                ->where('role', 'technician')
                ->whereNull('technician_id')
                ->select('id')
                ->orderBy('id')
                ->get()
                ->each(function ($user): void {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['technician_id' => User::formatTechnicianId((int) $user->id)]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'technician_id')) {
                $table->dropUnique('users_technician_id_unique');
                $table->dropColumn('technician_id');
            }

            if (Schema::hasColumn('users', 'customer_id')) {
                $table->dropUnique('users_customer_id_unique');
                $table->dropColumn('customer_id');
            }
        });
    }
};
