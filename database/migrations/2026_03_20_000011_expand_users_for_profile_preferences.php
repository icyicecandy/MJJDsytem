<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('address');
            }

            if (! Schema::hasColumn('users', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('bio');
            }

            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 30)->nullable()->after('date_of_birth');
            }

            if (! Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('gender');
            }

            if (! Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'language')) {
                $table->string('language', 10)->default('en')->after('avatar_path');
            }

            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->default(config('app.timezone', 'UTC'))->after('language');
            }

            if (! Schema::hasColumn('users', 'theme_preference')) {
                $table->string('theme_preference', 10)->default('system')->after('timezone');
            }

            if (! Schema::hasColumn('users', 'email_notifications')) {
                $table->boolean('email_notifications')->default(true)->after('theme_preference');
            }

            if (! Schema::hasColumn('users', 'push_notifications')) {
                $table->boolean('push_notifications')->default(false)->after('email_notifications');
            }

            if (! Schema::hasColumn('users', 'sms_notifications')) {
                $table->boolean('sms_notifications')->default(false)->after('push_notifications');
            }

            if (! Schema::hasColumn('users', 'profile_visibility')) {
                $table->string('profile_visibility', 20)->default('private')->after('sms_notifications');
            }

            if (! Schema::hasColumn('users', 'data_sharing_enabled')) {
                $table->boolean('data_sharing_enabled')->default(false)->after('profile_visibility');
            }

            if (! Schema::hasColumn('users', 'deletion_requested_at')) {
                $table->timestamp('deletion_requested_at')->nullable()->after('data_sharing_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'bio',
                'date_of_birth',
                'gender',
                'avatar_path',
                'phone_verified_at',
                'language',
                'timezone',
                'theme_preference',
                'email_notifications',
                'push_notifications',
                'sms_notifications',
                'profile_visibility',
                'data_sharing_enabled',
                'deletion_requested_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
