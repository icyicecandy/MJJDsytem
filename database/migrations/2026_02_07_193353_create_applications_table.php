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
    Schema::create('applications', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')->constrained()->onDelete('cascade');

        $table->string('reference_no')->unique();

        // Applicant info
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email');
        $table->string('mobile_number');

        // Address
        $table->string('municipality');
        $table->string('barangay');
        $table->string('street_landmark')->nullable();

        // Location pin (optional for now)
        $table->decimal('lat', 10, 7)->nullable();
        $table->decimal('lng', 10, 7)->nullable();

        // Plan
        $table->string('plan_code'); // S2S_50 or S2S_100

        // ID Upload (optional for now)
        $table->string('id_image_path')->nullable();

        // Workflow status
        $table->string('status')->default('submitted');
        // submitted | under_review | approved | rejected | scheduled | installed

        $table->timestamps();
    });
 }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
