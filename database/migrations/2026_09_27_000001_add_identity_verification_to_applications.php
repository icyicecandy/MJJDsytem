<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('valid_id_type')->nullable()->after('id_selfie_image_path');
            $table->string('valid_id_type_other')->nullable()->after('valid_id_type');
            $table->text('valid_id_number')->nullable()->after('valid_id_type_other');
            $table->string('valid_id_number_fingerprint', 64)->nullable()->index()->after('valid_id_number');
            $table->string('id_image_hash', 64)->nullable()->index()->after('valid_id_number_fingerprint');
            $table->boolean('duplicate_id_number_detected')->default(false)->after('id_image_hash');
            $table->boolean('duplicate_id_image_detected')->default(false)->after('duplicate_id_number_detected');
            $table->string('id_verification_status')->nullable()->index()->after('duplicate_id_image_detected');
            $table->text('id_verification_reason')->nullable()->after('id_verification_status');
            $table->foreignId('id_verified_by')->nullable()->after('id_verification_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('id_verified_at')->nullable()->after('id_verified_by');
        });

        Schema::table('application_drafts', function (Blueprint $table) {
            $table->string('id_image_path')->nullable()->after('photo_path');
        });

        Schema::create('identity_document_claims', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 24);
            $table->string('fingerprint', 64);
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['kind', 'fingerprint']);
        });

        Schema::create('application_id_verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_id_verification_events');
        Schema::dropIfExists('identity_document_claims');

        Schema::table('application_drafts', function (Blueprint $table) {
            $table->dropColumn('id_image_path');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['id_verified_by']);
            $table->dropColumn([
                'valid_id_type',
                'valid_id_type_other',
                'valid_id_number',
                'valid_id_number_fingerprint',
                'id_image_hash',
                'duplicate_id_number_detected',
                'duplicate_id_image_detected',
                'id_verification_status',
                'id_verification_reason',
                'id_verified_by',
                'id_verified_at',
            ]);
        });
    }
};
