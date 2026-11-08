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

test('system logs expose email delivery and hide obsolete backup activity', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    SystemLog::create([
        'user_id' => $admin->id,
        'user_role' => 'admin',
        'action' => 'Download Backup',
        'description' => 'Obsolete backup event',
    ]);
    SystemLog::create([
        'user_id' => $admin->id,
        'user_role' => 'admin',
        'action' => 'Email Sent',
        'description' => 'Current email event',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.system-logs.index'))
        ->assertOk()
        ->assertSee('Log type')
        ->assertSee('Email Delivery')
        ->assertSee('Current email event')
        ->assertSee('data-log-module="email"', false)
        ->assertDontSee('Download Backup')
        ->assertDontSee('Obsolete backup event');
});

test('system log live filtering accepts combined activity filters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Jepsy Dave Mamugay',
    ]);

    SystemLog::create([
        'user_id' => $technician->id,
        'user_role' => 'technician',
        'action' => 'Account Logout',
        'description' => 'Jepsy Dave Mamugay logged out.',
    ]);

    $this->actingAs($admin)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('admin.system-logs.index', [
            'view' => 'activity',
            'search' => 'jep',
            'action' => 'Account Logout',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
        ], false))
        ->assertOk()
        ->assertSee('id="systemLogResults"', false)
        ->assertSee('Jepsy Dave Mamugay logged out.')
        ->assertSee('data-filter-page-switch', false)
        ->assertDontSee('Live filtering could not update.');
});
