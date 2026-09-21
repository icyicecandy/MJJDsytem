<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('urgency_review_required')->default(false)->after('urgency')->index();
            $table->timestamp('triaged_at')->nullable()->after('urgency_review_required');
            $table->unsignedBigInteger('triaged_by')->nullable()->after('triaged_at');
            $table->timestamp('assigned_at')->nullable()->after('technician_id');
            $table->string('assignment_source', 30)->nullable()->after('assigned_at');
            $table->timestamp('response_due_at')->nullable()->after('assignment_source')->index();
        });

        $responseHours = config('ticket-routing.response_hours');

        DB::table('tickets')
            ->orderBy('id')
            ->each(function ($ticket) use ($responseHours): void {
                $urgency = array_key_exists($ticket->urgency, $responseHours)
                    ? $ticket->urgency
                    : 'medium';
                $createdAt = Carbon::parse($ticket->created_at);
                $updates = [
                    'response_due_at' => $createdAt->copy()->addHours($responseHours[$urgency]),
                ];

                if ($ticket->technician_id) {
                    $updates['assigned_at'] = $ticket->updated_at ?: $ticket->created_at;
                    $updates['assignment_source'] = 'legacy';
                }

                DB::table('tickets')->where('id', $ticket->id)->update($updates);
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'urgency_review_required',
                'triaged_at',
                'triaged_by',
                'assigned_at',
                'assignment_source',
                'response_due_at',
            ]);
        });
    }
};
