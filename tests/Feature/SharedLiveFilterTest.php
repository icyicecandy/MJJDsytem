<?php

use App\Models\User;

it('enables shared live filtering on admin operational lists', function (string $routeName, string $target) {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route($routeName, absolute: false))
        ->assertOk()
        ->assertSee('data-live-filter', false)
        ->assertSee('data-live-filter-target="#'.$target.'"', false)
        ->assertSee('id="'.$target.'"', false)
        ->assertSee('data-live-filter-results', false)
        ->assertSee('js/live-filters.js', false);
})->with([
    'support tickets' => ['admin.tickets.index', 'adminTicketResults'],
    'customers' => ['admin.customers.index', 'customerResults'],
    'technicians' => ['admin.technicians.index', 'technicianResults'],
    'system logs' => ['admin.system-logs.index', 'systemLogResults'],
]);

it('enables shared live filtering on technician tickets and chat', function () {
    $technician = User::factory()->create(['role' => 'technician']);

    $this->actingAs($technician)
        ->get(route('technician.tickets.index', absolute: false))
        ->assertOk()
        ->assertSee('data-live-filter-target="#technicianTicketResults"', false)
        ->assertSee('id="technicianTicketResults"', false)
        ->assertSee('data-live-filter-results', false);

    $this->get(route('role.chat.index', ['role' => 'technician'], false))
        ->assertOk()
        ->assertSee('data-live-filter-target="#chat-conversation-list"', false)
        ->assertSee('data-live-filter-preserve-node', false);
});

it('enables shared live filtering on customer tickets', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('customer.tickets.index', absolute: false))
        ->assertOk()
        ->assertSee('data-live-filter-target="#customerTicketResults"', false)
        ->assertSee('id="customerTicketResults"', false)
        ->assertSee('data-live-filter-results', false);
});

it('enables shared live filtering on the full notifications page for every role', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('notifications.index', absolute: false))
        ->assertOk()
        ->assertSee('data-live-filter-target="#notificationResults"', false)
        ->assertSee('id="notificationResults"', false)
        ->assertSee('data-live-filter-results', false);
})->with(['admin', 'technician', 'customer']);

it('enables shared live filtering on administrator chat', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('role.chat.index', ['role' => 'admin'], false))
        ->assertOk()
        ->assertSee('data-live-filter-target="#chat-conversation-list"', false)
        ->assertSee('id="chat-conversation-list"', false)
        ->assertSee('data-live-filter-preserve-node', false);
});