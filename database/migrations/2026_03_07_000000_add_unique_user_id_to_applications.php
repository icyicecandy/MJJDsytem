<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // if duplicates exist we remove extras before enforcing unique
        if (Schema::hasTable('applications')) {
            $duplicates = \DB::table('applications')
                ->select('user_id', \DB::raw('COUNT(*) as c'))
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $dup) {
                // keep the earliest record, delete others
                \DB::table('applications')
                    ->where('user_id', $dup->user_id)
                    ->orderBy('id')
                    ->skip(1)
                    ->take($dup->c - 1)
                    ->delete();
            }
        }

        Schema::table('applications', function (Blueprint $table) {
            // ensure one application per user
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
