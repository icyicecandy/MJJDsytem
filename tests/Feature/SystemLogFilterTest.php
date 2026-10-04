<?php

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

test('administrator can combine system log filters and retain responsive records', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer', 'name' => 'Filter Customer']);

    SystemLog::create([
        'user_id' => $customer->id,
        'user_role' => 'customer',
        'action' => 'Application Submitted',
        'description' => 'Visible application audit record',
    ]);
    SystemLog::create([
        'user_id' => $customer->id,
        'user_role' => 'customer',
        'action' => 'Ticket Created',
        'description' => 'Hidden ticket audit record',
    ]);
    SystemLog::create([
        'user_id' => null,
        'user_role' => 'system',
        'action' => 'Maintenance Completed',
        'description' => 'Hidden automated record',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.system-logs.index', [
            'module' => 'application',
            'user_id' => $customer->id,
            'search' => 'audit',
        ], false))
        ->assertOk()

        ->assertViewHas('activeFilterCount', 3)
        ->assertSee('Visible application audit record')
        ->assertDontSee('Hidden ticket audit record')
        ->assertDontSee('Hidden automated record')
        ->assertSee('Read-only audit trail')
        ->assertSee('data-system-log-page', false)
        ->assertSee('data-log-module="application"', false)
        ->assertSee('data-mobile-filter', false)
        ->assertSee('data-mobile-cards-expandable', false);
});

test('system logs are paginated and preserve filter query parameters', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (range(1, 30) as $index) {
        SystemLog::create([
            'user_id' => $admin->id,
            'user_role' => 'admin',
            'action' => 'Profile Updated',
            'description' => "Profile audit {$index}",
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.system-logs.index', ['action' => 'Profile Updated'], false))
        ->assertOk()
        ->assertViewHas('logs', function ($logs): bool {
            return $logs instanceof LengthAwarePaginator
                && $logs->count() === 25
                && $logs->total() === 30
                && str_contains($logs->nextPageUrl() ?? '', 'action=Profile%20Updated');
        })

        ->assertSee('Page 1 of 2');
});