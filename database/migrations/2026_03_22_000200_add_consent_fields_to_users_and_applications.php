<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('agreed_to_terms')->default(false)->after('email_verified_at');
            $table->boolean('agreed_to_privacy')->default(false)->after('agreed_to_terms');
            $table->timestamp('consented_at')->nullable()->after('agreed_to_privacy');
            $table->string('terms_version', 50)->nullable()->after('consented_at');
            $table->string('privacy_version', 50)->nullable()->after('terms_version');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->boolean('agreed_to_terms')->default(false)->after('id_image_path');
            $table->boolean('agreed_to_privacy')->default(false)->after('agreed_to_terms');
            $table->timestamp('consented_at')->nullable()->after('agreed_to_privacy');
            $table->string('terms_version', 50)->nullable()->after('consented_at');
            $table->string('privacy_version', 50)->nullable()->after('terms_version');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'agreed_to_terms',
                'agreed_to_privacy',
                'consented_at',
                'terms_version',
                'privacy_version',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'agreed_to_terms',
                'agreed_to_privacy',
                'consented_at',
                'terms_version',
                'privacy_version',
            ]);
        });
    }
};
