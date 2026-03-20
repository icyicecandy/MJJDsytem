<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_online')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_online')->default(false)->after('role');
                $table->timestamp('last_seen')->nullable()->after('is_online');
                $table->boolean('chat_available')->default(true)->after('last_seen');
            });
        }

        if ($this->usesMysqlDriver()) {
            DB::statement('ALTER TABLE chat_messages MODIFY from_user_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE chat_messages MODIFY to_user_id BIGINT UNSIGNED NULL');
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_messages', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('chat_messages', 'assigned_staff_id')) {
                $table->foreignId('assigned_staff_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('chat_messages', 'sender_type')) {
                $table->string('sender_type', 20)->default('agent')->after('message');
            }
            if (! Schema::hasColumn('chat_messages', 'message_type')) {
                $table->string('message_type', 20)->default('text')->after('sender_type');
            }
            if (! Schema::hasColumn('chat_messages', 'conversation_status')) {
                $table->string('conversation_status', 30)->default('human_assigned')->after('message_type');
            }
            if (! Schema::hasColumn('chat_messages', 'handoff_status')) {
                $table->string('handoff_status', 30)->nullable()->after('conversation_status');
            }
            if (! Schema::hasColumn('chat_messages', 'metadata')) {
                $table->json('metadata')->nullable()->after('handoff_status');
            }
        });

        if (! Schema::hasTable('faq_responses')) {
            Schema::create('faq_responses', function (Blueprint $table) {
                $table->id();
                $table->string('question');
                $table->text('keywords');
                $table->text('answer');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            DB::table('faq_responses')->insert([
                [
                    'question' => 'What internet plans do you offer?',
                    'keywords' => 'plan,plans,price,pricing,internet,offer,surf2sawa,50 mbps,100 mbps',
                    'answer' => 'MJJD Telecom currently offers Surf2Sawa prepaid fiber plans including 50 Mbps and 100 Mbps options. You can review and select the available plan during your application form.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'question' => 'How long does installation take?',
                    'keywords' => 'installation,install,schedule,how long,process,technician',
                    'answer' => 'Installation depends on review and technician scheduling. Once your application is approved, MJJD Telecom schedules the installation visit and you can monitor the progress from your customer portal.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'question' => 'How do I track my application?',
                    'keywords' => 'track,tracking,application,status,reference',
                    'answer' => 'You can track your application using the tracking page with your application reference number and your registered email or mobile number. You can also review the status from your customer dashboard.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'question' => 'How do I file a support ticket?',
                    'keywords' => 'ticket,support,report,issue,problem,help',
                    'answer' => 'You can file a support ticket from the customer portal under Support Tickets. Provide a clear subject and description so our support staff can respond faster.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'question' => 'What areas do you serve?',
                    'keywords' => 'area,areas,serve,serviceability,luzon,coverage,municipality,barangay',
                    'answer' => 'MJJD Telecom currently reviews applications within our supported Luzon coverage area. During application, please pin your exact installation location so the team can verify serviceability.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('faq_responses')) {
            Schema::dropIfExists('faq_responses');
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('chat_messages', 'handoff_status')) {
                $table->dropColumn('handoff_status');
            }
            if (Schema::hasColumn('chat_messages', 'conversation_status')) {
                $table->dropColumn('conversation_status');
            }
            if (Schema::hasColumn('chat_messages', 'message_type')) {
                $table->dropColumn('message_type');
            }
            if (Schema::hasColumn('chat_messages', 'sender_type')) {
                $table->dropColumn('sender_type');
            }
            if (Schema::hasColumn('chat_messages', 'assigned_staff_id')) {
                $table->dropConstrainedForeignId('assigned_staff_id');
            }
            if (Schema::hasColumn('chat_messages', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });

        if ($this->usesMysqlDriver()) {
            DB::statement('ALTER TABLE chat_messages MODIFY from_user_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE chat_messages MODIFY to_user_id BIGINT UNSIGNED NOT NULL');
        }

        if (Schema::hasColumn('users', 'chat_available')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['is_online', 'last_seen', 'chat_available']);
            });
        }
    }

    private function usesMysqlDriver(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }
};
