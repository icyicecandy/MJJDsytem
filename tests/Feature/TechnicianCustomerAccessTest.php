<?php

use App\Models\Application;
use App\Models\Ticket;
use App\Models\User;

test('technicians can browse and search the shared customer records', function () {
    $technician = User::factory()->create([
        'role' => 'technician',
        'must_change_password' => false,
    ]);
    $customer = User::factory()->create([
        'role' => 'customer',
        'name' => 'Field Service Customer',
        'phone' => '09171234567',
        'address' => 'Pogo, Bautista, Pangasinan',
    ]);

    Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-TECH-CUSTOMER',
        'first_name' => 'Field Service',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bautista',
        'barangay' => 'Pogo',
        'street_landmark' => 'Near municipal hall',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_APPROVED,
    ]);

    $this->actingAs($technician)
        ->get(route('technician.customers.index', ['search' => 'Field Service'], false))
        ->assertOk()
        ->assertSee('Customers')
        ->assertSee('Field Service')
        ->assertSee(route('technician.customers.show', $customer, false), false)
        ->assertSee(route('technician.customers.index', absolute: false), false);
});

test('technician customer profiles are read only and keep links inside technician routes', function () {
    $technician = User::factory()->create([
        'role' => 'technician',
        'must_change_password' => false,
    ]);
    $customer = User::factory()->create([
        'role' => 'customer',
        'name' => 'Assigned Customer',
    ]);

    Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-READONLY',
        'first_name' => 'Assigned',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'mobile_number' => '09170000000',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bautista',
        'barangay' => 'Pogo',
        'street_landmark' => 'Near the barangay hall',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_APPROVED,
    ]);

    $ticket = Ticket::create([
        'user_id' => $customer->id,
        'assigned_staff_id' => $technician->id,
        'technician_id' => $technician->id,
        'subject' => 'No Internet Connection',
        'description' => 'Assigned customer fixture.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
        'urgency_review_required' => false,
        'response_due_at' => now()->addHours(4),
    ]);

    $this->actingAs($technician)
        ->get(route('technician.customers.show', $customer, false))
        ->assertOk()
        ->assertSee('Assigned Customer')
        ->assertSee('Print Customer Summary')
        ->assertSee(route('technician.customers.print', $customer, false), false)
        ->assertSee(route('technician.tickets.show', $ticket, false), false)
        ->assertDontSee('Open Application')
        ->assertDontSee(route('admin.customers.show', $customer, false), false);

    $this->actingAs($technician)
        ->get(route('technician.customers.print', $customer, false))
        ->assertOk()
        ->assertSee('Customer Summary')
        ->assertSee(route('technician.customers.show', $customer, false), false);
});

test('customers cannot open technician customer records', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('technician.customers.index', absolute: false))
        ->assertForbidden();

    $this->actingAs($customer)
        ->get(route('technician.customers.show', $customer, false))
        ->assertForbidden();
});

test('technician customer pagination uses the compact shared controls', function () {
    $technician = User::factory()->create([
        'role' => 'technician',
        'must_change_password' => false,
    ]);
    User::factory()->count(13)->create(['role' => 'customer']);

    $this->actingAs($technician)
        ->get(route('technician.customers.index', absolute: false))
        ->assertOk()
        ->assertSee('Page 1 of 2')
        ->assertSee('Previous')
        ->assertSee('Next')
        ->assertSee('rel="next"', false);
});
