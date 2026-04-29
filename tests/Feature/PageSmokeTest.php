<?php

use App\Models\Application;
use App\Models\User;

test('high visibility pages load without obvious failures', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-9901',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane9901@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'submitted',
    ]);

    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create(['role' => 'technician']);
    $newCustomer = User::factory()->create(['role' => 'customer']);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-open-register-modal', false)
        ->assertSee('&#8369;999', false)
        ->assertSee('&#8369;1,699', false)
        ->assertDontSee('&#8369;1,000', false);

    $this->actingAs($admin)
        ->get('/')
        ->assertRedirect(route('dashboard', absolute: false));
    $this->post('/logout');

    $this->actingAs($newCustomer)
        ->get('/customer/apply')
        ->assertOk()
        ->assertSee('PHP 999 installation fee')
        ->assertSee('PHP 1,699 installation fee')
        ->assertDontSee('PHP 1,000 installation fee');
    $this->post('/logout');

    $this->actingAs($customer)->get('/customer/dashboard')->assertOk();
    $this->actingAs($customer)->get('/customer/application')->assertOk();
    $this->actingAs($customer)->get('/profile')->assertOk();
    $this->post('/logout');

    $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    $this->actingAs($admin)->get(route('admin.customers.index', absolute: false))->assertOk();
    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer, false))
        ->assertOk()
        ->assertSee('Customer Information');
    $this->actingAs($admin)->get(route('admin.installations.index', absolute: false))->assertOk()->assertSee('Scheduled');
    $this->actingAs($admin)
        ->get(route('admin.applications.show', Application::first(), false))
        ->assertOk()
        ->assertSee('Application Details');
    $this->post('/logout');

    $this->actingAs($technician)->get('/technician/dashboard')->assertOk();
    $this->post('/logout');

    $this->get('/track-application')->assertOk();
    $this->get('/track-ticket')->assertOk();
});
