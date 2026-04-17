<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->after('id');
        });

        \App\Models\Ticket::query()
            ->whereNull('tracking_number')
            ->orderBy('id')
            ->get()
            ->each(function (\App\Models\Ticket $ticket): void {
                $ticket->forceFill([
                    'tracking_number' => \App\Models\Ticket::generateTrackingNumber($ticket->id, $ticket->created_at),
                ])->saveQuietly();
            });

        Schema::table('tickets', function (Blueprint $table) {
            $table->unique('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('tickets_tracking_number_unique');
            $table->dropColumn('tracking_number');
        });
    }
};
