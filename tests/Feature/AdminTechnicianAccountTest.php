<?php

use App\Models\User;

test('admin can disable and enable a technician account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'technician_shift' => User::TECHNICIAN_STANDARD_SHIFT,
        'technician_status' => 'available',
        'is_online' => true,
        'chat_available' => true,
        'last_seen' => now(),
    ]);

    $disableResponse = $this
        ->actingAs($admin)
        ->patch(route('admin.technicians.disable', $technician, false));

    $disableResponse
        ->assertRedirect(route('admin.technicians.show', $technician, false))
        ->assertSessionHas('status', 'technician-disabled');

    $technician->refresh();

    expect($technician->isDisabled())->toBeTrue()
        ->and($technician->disabled_by)->toBe($admin->id)
        ->and($technician->is_online)->toBeFalse()
        ->and($technician->chat_available)->toBeFalse()
        ->and($technician->technician_status)->toBe('off_duty');

    $enableResponse = $this
        ->actingAs($admin)
        ->patch(route('admin.technicians.enable', $technician, false));

    $enableResponse
        ->assertRedirect(route('admin.technicians.show', $technician, false))
        ->assertSessionHas('status', 'technician-enabled');

    expect($technician->refresh()->isDisabled())->toBeFalse()
        ->and($technician->disabled_by)->toBeNull()
        ->and($technician->technician_status)->toBe('available');
});

test('technician detail exposes admin account access control', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'technician_shift' => User::TECHNICIAN_STANDARD_SHIFT,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.technicians.show', $technician, false))
        ->assertOk()
        ->assertSee('Account Access')
        ->assertSee('Disable Account')
        ->assertSee(User::TECHNICIAN_STANDARD_SHIFT);
});

test('technician records show live chat availability state', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $availableTechnician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Available Tech',
        'is_online' => true,
        'chat_available' => true,
        'last_seen' => now(),
    ]);

    $awayTechnician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Away Tech',
        'is_online' => true,
        'chat_available' => false,
        'last_seen' => now(),
    ]);

    $offlineTechnician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Offline Tech',
        'is_online' => false,
        'chat_available' => false,
        'last_seen' => null,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.technicians.index', absolute: false))
        ->assertOk()
        ->assertSee($availableTechnician->displayTechnicianRecordId())
        ->assertSee('Chat: Available')
        ->assertSee($awayTechnician->displayTechnicianRecordId())
        ->assertSee('Chat: Away')
        ->assertSee($offlineTechnician->displayTechnicianRecordId())
        ->assertSee('Chat: Offline');
});

test('disabled authenticated account is signed out before accessing protected pages', function () {
    $technician = User::factory()->create([
        'role' => 'technician',
        'disabled_at' => now(),
    ]);

    $response = $this
        ->actingAs($technician)
        ->get(route('technician.dashboard', absolute: false));

    $response
        ->assertRedirect('/')
        ->assertSessionHasErrors('email', null, 'login');

    $this->assertGuest();
});

test('technicians cannot disable technician accounts', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $otherTechnician = User::factory()->create(['role' => 'technician']);

    $this
        ->actingAs($technician)
        ->patch(route('admin.technicians.disable', $otherTechnician, false))
        ->assertForbidden();

    expect($otherTechnician->refresh()->isDisabled())->toBeFalse();
});
