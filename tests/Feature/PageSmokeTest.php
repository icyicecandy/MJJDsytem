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

    $this->actingAs($customer)->get('/customer/dashboard')->assertOk();
    $this->actingAs($customer)->get('/customer/application')->assertOk();
    $this->actingAs($customer)->get('/profile')->assertOk();
    $this->post('/logout');

    $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
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
