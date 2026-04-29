<?php

use App\Models\User;

test('customer and technician record ids are generated from their user ids', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $admin = User::factory()->create(['role' => 'admin']);

    expect($customer->refresh()->customer_id)->toBe(User::formatCustomerId((int) $customer->id))
        ->and($customer->technician_id)->toBeNull()
        ->and($technician->refresh()->technician_id)->toBe(User::formatTechnicianId((int) $technician->id))
        ->and($technician->customer_id)->toBeNull()
        ->and($admin->refresh()->customer_id)->toBeNull()
        ->and($admin->technician_id)->toBeNull();
});

test('admin customer and technician records display and search by role id', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer', 'name' => 'Jane Doe']);
    $technician = User::factory()->create(['role' => 'technician', 'name' => 'Juan Dela Cruz']);

    $this
        ->actingAs($admin)
        ->get(route('admin.customers.index', ['search' => $customer->customer_id], false))
        ->assertOk()
        ->assertSee('First Name')
        ->assertSee('Last Name')
        ->assertSee('Jane')
        ->assertSee('Doe')
        ->assertSee($customer->customer_id);

    $this
        ->actingAs($admin)
        ->get(route('admin.customers.show', $customer, false))
        ->assertOk()
        ->assertSee('Customer ID')
        ->assertSee($customer->customer_id);

    $this
        ->actingAs($admin)
        ->get(route('admin.technicians.index', ['search' => $technician->technician_id], false))
        ->assertOk()
        ->assertSee('First Name')
        ->assertSee('Last Name')
        ->assertSee('Juan')
        ->assertSee('Dela Cruz')
        ->assertSee($technician->technician_id)
        ->assertDontSee('This list keeps the overview lean. Open a technician record to review workload, assigned installations, open tickets, and schedule details.');

    $this
        ->actingAs($admin)
        ->get(route('admin.technicians.show', $technician, false))
        ->assertOk()
        ->assertSee('Technician ID')
        ->assertSee($technician->technician_id);
});
