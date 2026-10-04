<?php

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'api.pwnedpasswords.com/*' => Http::response('', 200),
    ]);
});

function technicianRequiringPasswordChange(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'technician',
        'password' => Hash::make('TemporaryKey123!'),
        'password_set_at' => null,
        'must_change_password' => true,
        'is_online' => false,
        'chat_available' => false,
        'technician_status' => 'available',
    ], $attributes));
}

test('technician using a temporary account key is sent directly to the required password screen', function () {
    $technician = technicianRequiringPasswordChange();

    $response = $this->post('/login', [
        'email' => $technician->email,
        'password' => 'TemporaryKey123!',
    ]);

    $response->assertRedirect(route('technician.password.change', absolute: false));
    $this->assertAuthenticatedAs($technician);

    $technician->refresh();

    expect($technician->must_change_password)->toBeTrue()
        ->and($technician->is_online)->toBeFalse()
        ->and($technician->chat_available)->toBeFalse()
        ->and($technician->last_seen)->toBeNull();
});

test('required password screen explains the temporary key workflow', function () {
    $technician = technicianRequiringPasswordChange(['name' => 'Raffy Dela Cruz']);

    $this->actingAs($technician)
        ->get(route('technician.password.change', absolute: false))
        ->assertOk()
        ->assertSee('Required first-login step')
        ->assertSee('Create your private password')
        ->assertSee('Temporary account key')
        ->assertSee('Set New Password and Continue')
        ->assertSee('You cannot access technician records until this security step is complete.')
        ->assertSee(route('technician.password.change.update', absolute: false), false);
});

test('technician portal stays blocked until the required password change is completed', function () {
    $technician = technicianRequiringPasswordChange();

    $this->actingAs($technician)
        ->get(route('technician.dashboard', absolute: false))
        ->assertRedirect(route('technician.password.change', absolute: false));

    $this->actingAs($technician)
        ->getJson(route('technician.tickets.index', absolute: false))
        ->assertStatus(423)
        ->assertJsonPath('redirect', route('technician.password.change'));
});

test('wrong temporary key and reused temporary key are rejected', function () {
    $technician = technicianRequiringPasswordChange();

    $this->actingAs($technician)
        ->from(route('technician.password.change', absolute: false))
        ->put(route('technician.password.change.update', absolute: false), [
            'current_password' => 'WrongTemporaryKey!',
            'password' => 'PrivatePassword456!',
            'password_confirmation' => 'PrivatePassword456!',
        ])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($technician)
        ->from(route('technician.password.change', absolute: false))
        ->put(route('technician.password.change.update', absolute: false), [
            'current_password' => 'TemporaryKey123!',
            'password' => 'TemporaryKey123!',
            'password_confirmation' => 'TemporaryKey123!',
        ])
        ->assertSessionHasErrors('password');

    expect($technician->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check('TemporaryKey123!', $technician->fresh()->password))->toBeTrue();
});

test('successful first password change unlocks the portal and invalidates the temporary key', function () {
    $technician = technicianRequiringPasswordChange();
    $newPassword = 'PrivatePassword456!';

    $response = $this->actingAs($technician)
        ->put(route('technician.password.change.update', absolute: false), [
            'current_password' => 'TemporaryKey123!',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

    $response
        ->assertRedirect(route('technician.dashboard', absolute: false))
        ->assertSessionHas('status', 'first-login-password-updated');

    $technician->refresh();

    expect($technician->must_change_password)->toBeFalse()
        ->and($technician->password_set_at)->not->toBeNull()
        ->and($technician->is_online)->toBeTrue()
        ->and($technician->chat_available)->toBeTrue()
        ->and($technician->last_seen)->not->toBeNull()
        ->and(Hash::check($newPassword, $technician->password))->toBeTrue()
        ->and(Hash::check('TemporaryKey123!', $technician->password))->toBeFalse();

    $this->get(route('technician.dashboard', absolute: false))->assertOk();

    expect(SystemLog::query()
        ->where('user_id', $technician->id)
        ->where('action', 'Technician Initial Password Changed')
        ->exists())->toBeTrue();
});

test('existing technicians and other account roles are not forced into the first login flow', function () {
    $existingTechnician = User::factory()->create([
        'role' => 'technician',
        'must_change_password' => false,
        'password_set_at' => now(),
    ]);

    $this->actingAs($existingTechnician)
        ->get(route('technician.dashboard', absolute: false))
        ->assertOk();

    foreach (['admin', 'customer'] as $role) {
        $user = User::factory()->create([
            'role' => $role,
            'must_change_password' => true,
        ]);

        expect($user->requiresPasswordChange())->toBeFalse();
    }
});
