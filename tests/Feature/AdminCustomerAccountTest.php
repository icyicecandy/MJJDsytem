<?php

use App\Models\SystemLog;
use App\Models\User;

test('administrator can disable and enable a customer account with an audit trail', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create([
        'role' => 'customer',
        'is_online' => true,
        'chat_available' => true,
        'last_seen' => now(),
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.customers.disable', $customer, false), [
            'disabled_reason' => 'Identity ownership requires manual review.',
        ])
        ->assertRedirect(route('admin.customers.show', $customer, false))
        ->assertSessionHas('status', 'customer-disabled');

    $customer->refresh();

    expect($customer->isDisabled())->toBeTrue()
        ->and($customer->disabled_by)->toBe($admin->id)
        ->and($customer->disabled_reason)->toBe('Identity ownership requires manual review.')
        ->and($customer->is_online)->toBeFalse()
        ->and($customer->chat_available)->toBeFalse()
        ->and(SystemLog::query()
            ->where('user_id', $admin->id)
            ->where('action', 'Customer Account Disabled')
            ->where('description', 'like', '%'.$customer->displayCustomerRecordId().'%')
            ->exists())->toBeTrue();

    $this->patch(route('admin.customers.enable', $customer, false))
        ->assertRedirect(route('admin.customers.show', $customer, false))
        ->assertSessionHas('status', 'customer-enabled');

    expect($customer->refresh()->isDisabled())->toBeFalse()
        ->and($customer->disabled_by)->toBeNull()
        ->and($customer->disabled_reason)->toBeNull()
        ->and(SystemLog::query()
            ->where('user_id', $admin->id)
            ->where('action', 'Customer Account Enabled')
            ->exists())->toBeTrue();
});

test('administrator must record a reason before disabling a customer account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($admin)
        ->from(route('admin.customers.show', $customer, false))
        ->patch(route('admin.customers.disable', $customer, false), [
            'disabled_reason' => '',
        ])
        ->assertRedirect(route('admin.customers.show', $customer, false))
        ->assertSessionHasErrors('disabled_reason');

    expect($customer->refresh()->isDisabled())->toBeFalse();
});

test('customer access controls are visible only to administrators', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'must_change_password' => false,
    ]);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer, false))
        ->assertOk()
        ->assertSee('Admin-only access controls for this customer account')
        ->assertSee('Disable Account');

    $this->actingAs($technician)
        ->get(route('technician.customers.show', $customer, false))
        ->assertOk()
        ->assertDontSee('Admin-only access controls for this customer account')
        ->assertDontSee(route('admin.customers.disable', $customer, false), false);

    $this->actingAs($technician)
        ->patch(route('admin.customers.disable', $customer, false), [
            'disabled_reason' => 'Unauthorized attempt.',
        ])
        ->assertForbidden();

    expect($customer->refresh()->isDisabled())->toBeFalse();
});
