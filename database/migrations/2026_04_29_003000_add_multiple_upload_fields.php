<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'id_selfie_image_path')) {
                $table->string('id_selfie_image_path')->nullable()->after('id_image_path');
            }
        });

        Schema::table('installations', function (Blueprint $table) {
            if (! Schema::hasColumn('installations', 'installation_photo_paths')) {
                $table->json('installation_photo_paths')->nullable()->after('installation_photo_path');
            }
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'photo_paths')) {
                $table->json('photo_paths')->nullable()->after('photo_path');
            }
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_messages', 'attachment_paths')) {
                $table->json('attachment_paths')->nullable()->after('attachment_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_messages', 'attachment_paths')) {
                $table->dropColumn('attachment_paths');
            }
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'photo_paths')) {
                $table->dropColumn('photo_paths');
            }
        });

        Schema::table('installations', function (Blueprint $table) {
            if (Schema::hasColumn('installations', 'installation_photo_paths')) {
                $table->dropColumn('installation_photo_paths');
            }
        });

        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'id_selfie_image_path')) {
                $table->dropColumn('id_selfie_image_path');
            }
        });
    }
};
