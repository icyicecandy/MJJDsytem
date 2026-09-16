<?php

use App\Support\ApplicationContact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deploy with application writes paused. Never merge or delete old records.
        $rows = DB::table('applications')->get(['id', 'reference_no', 'email', 'mobile_number']);
        Schema::table('applications', function (Blueprint $table) {
            $table->string('email_key')->nullable();
            $table->string('mobile_number_key')->nullable();
        });
        foreach ($rows as $row) {
            DB::table('applications')->where('id', $row->id)->update([
                'email_key' => ApplicationContact::key('email', $row->email),
                'mobile_number_key' => ApplicationContact::key('mobile_number', $row->mobile_number),
            ]);
        }
        Schema::table('applications', function (Blueprint $table) {
            $table->string('email_key')->nullable(false)->change();
            $table->string('mobile_number_key')->nullable(false)->change();
            $table->index('email_key');
            $table->index('mobile_number_key');
        });
        // A stable row exists even before the first application is submitted.
        // This also preserves pre-existing duplicates without assigning ownership.
        Schema::create('application_contact_guard', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('application_contact_guard')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('application_contact_guard');
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['email_key']);
            $table->dropIndex(['mobile_number_key']);
            $table->dropColumn(['email_key', 'mobile_number_key']);
        });
    }
};
