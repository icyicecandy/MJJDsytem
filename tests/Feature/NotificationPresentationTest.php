<?php

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Carbon;

test('notification feed includes stable date grouping labels', function () {
    Carbon::setTestNow('2026-09-22 10:00:00');
    $user = User::factory()->create(['role' => 'customer']);

    $today = AppNotification::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Today notice',
        'message' => 'Current activity',
        'url' => route('dashboard'),
    ]);
    $yesterday = AppNotification::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Yesterday notice',
        'message' => 'Earlier activity',
        'url' => route('dashboard'),
    ]);
    $yesterday->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ])->saveQuietly();

    $response = $this->actingAs($user)->getJson(route('notifications.feed', absolute: false));

    $response->assertOk()
        ->assertJsonPath('all_notifications.0.id', $today->id)
        ->assertJsonPath('all_notifications.0.date_key', '2026-09-22')
        ->assertJsonPath('all_notifications.0.date_label', 'Today')
        ->assertJsonPath('all_notifications.1.id', $yesterday->id)
        ->assertJsonPath('all_notifications.1.date_key', '2026-09-21')
        ->assertJsonPath('all_notifications.1.date_label', 'Yesterday');
});

test('notification feed groups unread updates into the matching admin sidebar sections', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach ([
        ['application_submitted', '/admin/applications/1'],
        ['installation_completed_admin', '/admin/installations/1'],
        ['technician_unavailability_submitted', '/admin/technician-unavailability/1'],
        ['ticket_submitted', '/admin/tickets/1'],
        ['chat_message_staff', '/admin/chat/1'],
    ] as [$type, $url]) {
        AppNotification::create([
            'user_id' => $admin->id,
            'type' => $type,
            'title' => 'New update',
            'message' => 'A record needs attention.',
            'url' => $url,
        ]);
    }

    AppNotification::create([
        'user_id' => $admin->id,
        'type' => 'ticket_status',
        'title' => 'Already reviewed',
        'url' => '/admin/tickets/2',
        'read_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson(route('notifications.feed', absolute: false))
        ->assertOk()
        ->assertJsonPath('unread_count', 5)
        ->assertJsonPath('sidebar_counts.applications', 1)
        ->assertJsonPath('sidebar_counts.installations', 1)
        ->assertJsonPath('sidebar_counts.technicians', 1)
        ->assertJsonPath('sidebar_counts.tickets', 1)
        ->assertJsonPath('sidebar_counts.chat', 1);
});

test('customer and technician notifications map to the sidebar sections available to their roles', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);

    $customerApplication = AppNotification::create([
        'user_id' => $customer->id,
        'type' => 'installation_rescheduled_customer',
        'title' => 'Installation rescheduled',
    ]);
    $customerChat = AppNotification::create([
        'user_id' => $customer->id,
        'type' => 'chat_message_customer',
        'title' => 'New live chat reply',
    ]);
    $technicianAvailability = AppNotification::create([
        'user_id' => $technician->id,
        'type' => 'technician_unavailability_decision',
        'title' => 'Availability request reviewed',
    ]);
    $technicianInstallation = AppNotification::create([
        'user_id' => $technician->id,
        'type' => 'installation_reassigned',
        'title' => 'Installation assigned',
    ]);

    expect($customerApplication->sidebarSectionFor($customer))->toBe('applications')
        ->and($customerChat->sidebarSectionFor($customer))->toBe('chat')
        ->and($technicianAvailability->sidebarSectionFor($technician))->toBe('dashboard')
        ->and($technicianInstallation->sidebarSectionFor($technician))->toBe('installations');
});

test('marking a sidebar section read leaves unrelated notifications unread', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $ticket = AppNotification::create([
        'user_id' => $admin->id,
        'type' => 'ticket_submitted',
        'title' => 'New ticket',
    ]);
    $application = AppNotification::create([
        'user_id' => $admin->id,
        'type' => 'application_submitted',
        'title' => 'New application',
    ]);

    $this->actingAs($admin)
        ->postJson(route('notifications.mark-section-read', absolute: false), [
            'section' => 'tickets',
        ])
        ->assertOk()
        ->assertJsonPath('updated', 1)
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('sidebar_counts.applications', 1)
        ->assertJsonMissingPath('sidebar_counts.tickets');

    expect($ticket->refresh()->read_at)->not->toBeNull()
        ->and($application->refresh()->read_at)->toBeNull();
});

test('sidebar section endpoint rejects unknown sections', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson(route('notifications.mark-section-read', absolute: false), [
            'section' => 'unknown',
        ])
        ->assertUnprocessable();
});
