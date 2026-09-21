<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('technician_shift_start')->nullable()->after('technician_shift');
            $table->time('technician_shift_end')->nullable()->after('technician_shift_start');
            $table->json('technician_work_days')->nullable()->after('technician_shift_end');
        });

        $dayLabels = [
            'Mon' => 'mon',
            'Tue' => 'tue',
            'Wed' => 'wed',
            'Thu' => 'thu',
            'Fri' => 'fri',
        ];

        DB::table('users')
            ->where('role', 'technician')
            ->orderBy('id')
            ->each(function ($technician) use ($dayLabels): void {
                $workDays = array_values($dayLabels);

                if ($technician->technician_employment_type === 'part_time' && filled($technician->technician_shift)) {
                    $scheduleDays = str_contains($technician->technician_shift, ',')
                        ? trim(substr($technician->technician_shift, strpos($technician->technician_shift, ',') + 1))
                        : '';
                    $parsedDays = collect(preg_split('/\s*\/\s*/', $scheduleDays, -1, PREG_SPLIT_NO_EMPTY))
                        ->map(fn (string $label) => $dayLabels[trim($label)] ?? null)
                        ->filter()
                        ->values()
                        ->all();

                    if ($parsedDays !== []) {
                        $workDays = $parsedDays;
                    }
                }

                DB::table('users')->where('id', $technician->id)->update([
                    'technician_shift_start' => '08:00:00',
                    'technician_shift_end' => '17:00:00',
                    'technician_work_days' => json_encode($workDays),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'technician_shift_start',
                'technician_shift_end',
                'technician_work_days',
            ]);
        });
    }
};
