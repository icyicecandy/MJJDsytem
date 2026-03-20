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
        if (Schema::hasColumn('installations', 'job_status')) {
            Schema::table('installations', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->after('job_status');
                $table->string('installation_photo_path')->nullable();
                $table->string('speedtest_photo_path')->nullable();
                $table->string('nap_photo_path')->nullable();
            });
        } else {
            // fallback: just add columns at end if job_status is missing
            Schema::table('installations', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable();
                $table->string('installation_photo_path')->nullable();
                $table->string('speedtest_photo_path')->nullable();
                $table->string('nap_photo_path')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropColumn([
                'completed_at',
                'installation_photo_path',
                'speedtest_photo_path',
                'nap_photo_path',
            ]);
        });
    }
};
