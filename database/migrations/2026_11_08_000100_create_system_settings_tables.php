<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->unsignedInteger('speed_mbps');
            $table->decimal('installation_fee', 10, 2)->default(0);
            $table->text('description');
            $table->json('features')->nullable();
            $table->string('badge', 40)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('business_name', 120);
            $table->string('phone', 40);
            $table->string('support_email');
            $table->text('office_address');
            $table->string('business_hours', 255);
            $table->string('facebook_url')->nullable();
            $table->string('map_url')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('service_plans')->insert([
            [
                'code' => 'S2S_50',
                'name' => 'Superfiber',
                'speed_mbps' => 50,
                'installation_fee' => 999,
                'description' => 'Good for daily browsing, messaging, streaming, and home use.',
                'features' => json_encode(['Ideal for browsing and streaming', 'Up to 6 devices', 'Application review before installation']),
                'badge' => null,
                'is_featured' => false,
                'is_active' => true,
                'display_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'S2S_100',
                'name' => 'Extraboost',
                'speed_mbps' => 100,
                'installation_fee' => 1699,
                'description' => 'Higher-speed access for multiple users, devices, and heavier streaming.',
                'features' => json_encode(['Perfect for gaming and HD streaming', 'Up to 10 devices', 'Application review before installation']),
                'badge' => 'Most Popular',
                'is_featured' => true,
                'is_active' => true,
                'display_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('business_settings')->insert([
            'id' => 1,
            'business_name' => 'MJJD Telecom',
            'phone' => '0985 075 7176',
            'support_email' => 'support@mjjdtelecom.com',
            'office_address' => 'Dumlao St. Dusoc, Bayambang, Pangasinan',
            'business_hours' => 'Mon - Fri: 8:00 AM - 5:00 PM',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('plan_code')->constrained('service_plans')->nullOnDelete();
            $table->string('plan_name_snapshot', 100)->nullable()->after('plan_id');
            $table->unsignedInteger('plan_speed_mbps_snapshot')->nullable()->after('plan_name_snapshot');
            $table->decimal('plan_installation_fee_snapshot', 10, 2)->nullable()->after('plan_speed_mbps_snapshot');
            $table->text('plan_description_snapshot')->nullable()->after('plan_installation_fee_snapshot');
            $table->json('plan_features_snapshot')->nullable()->after('plan_description_snapshot');
        });

        foreach (DB::table('service_plans')->get() as $plan) {
            DB::table('applications')->where('plan_code', $plan->code)->update([
                'plan_id' => $plan->id,
                'plan_name_snapshot' => $plan->name,
                'plan_speed_mbps_snapshot' => $plan->speed_mbps,
                'plan_installation_fee_snapshot' => $plan->installation_fee,
                'plan_description_snapshot' => $plan->description,
                'plan_features_snapshot' => $plan->features,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn([
                'plan_name_snapshot',
                'plan_speed_mbps_snapshot',
                'plan_installation_fee_snapshot',
                'plan_description_snapshot',
                'plan_features_snapshot',
            ]);
        });

        Schema::dropIfExists('business_settings');
        Schema::dropIfExists('service_plans');
    }
};
