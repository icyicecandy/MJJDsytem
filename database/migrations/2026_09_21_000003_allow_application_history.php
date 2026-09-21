<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // MySQL may use the unique index for this column's foreign key.
            // Add a normal index first so removing uniqueness never removes
            // the index required by the foreign-key constraint.
            $table->index('user_id', 'applications_user_id_history_index');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique('applications_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->unique('user_id');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('applications_user_id_history_index');
        });
    }
};
