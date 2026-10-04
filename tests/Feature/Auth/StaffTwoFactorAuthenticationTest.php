<?php

use App\Models\SystemLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\StaffTwoFactorService;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

function configuredStaff(array $attributes = []): array
{
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey(32);
    $recoveryCode = 'MJJD-RECOVERY-01';

    $user = User::factory()->create(array_merge([
        'role' => 'admin',
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => [Hash::make('MJJDRECOVERY01')],
        'two_factor_confirmed_at' => now(),
        'remember_token' => null,
    ], $attributes));

    return [$user, $secret, $recoveryCode];
}

test('customers continue to sign in without a staff two-factor challenge', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $response = $this->post('/login', [
        'email' => $customer->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($customer);
    $response->assertRedirect('/customer/dashboard');
});

test('administrator and technician accounts must enroll before using staff pages', function (string $role) {
    $staff = User::factory()->create([
        'role' => $role,
        'must_change_password' => false,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this->post('/login', [
        'email' => $staff->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($staff);
    $response->assertRedirect(route('staff.two-factor.setup', absolute: false));

    $protected = $this->get($role === 'admin' ? '/admin/dashboard' : '/technician/dashboard');
    $protected->assertRedirect(route('staff.two-factor.setup', absolute: false));
})->with(['admin', 'technician']);

test('configured staff must pass a two-factor challenge on an untrusted browser', function () {
    [$admin] = configuredStaff();

    $response = $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
        'remember' => '1',
    ]);

    $this->assertGuest();
    $response
        ->assertRedirect(route('staff.two-factor.challenge', absolute: false))
        ->assertSessionHas('staff_two_factor.pending.user_id', $admin->id);
});

test('staff can enroll with a qr code and receives recovery codes', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $setup = $this->actingAs($admin)->get(route('staff.two-factor.setup'));
    $setup
        ->assertOk()
        ->assertSee('Secure your administrator account')
        ->assertSee('<svg', false);

    $secret = session('staff_two_factor.enrollment_secret');
    expect($secret)->toBeString()->not->toBeEmpty();

    $response = $this->post(route('staff.two-factor.confirm'), [
        'code' => (new Google2FA)->getCurrentOtp($secret),
    ]);

    $response
        ->assertRedirect(route('staff.two-factor.recovery-codes', absolute: false))
        ->assertSessionHas('staff_two_factor.new_recovery_codes');

    expect($admin->refresh()->hasStaffTwoFactorEnabled())->toBeTrue()
        ->and($admin->two_factor_recovery_codes)->toHaveCount(8);
});

test('a valid authenticator code completes staff sign in and can trust the browser', function () {
    [$admin, $secret] = configuredStaff();

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('staff.two-factor.challenge', absolute: false));

    $response = $this
        ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0')
        ->post(route('staff.two-factor.challenge.verify'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
            'trust_device' => '1',
        ]);

    $this->assertAuthenticatedAs($admin);
    $response
        ->assertRedirect(route('admin.dashboard', absolute: false))
        ->assertCookie(StaffTwoFactorService::TRUST_COOKIE);

    expect(TrustedDevice::query()->where('user_id', $admin->id)->active()->count())->toBe(1)
        ->and(SystemLog::query()
            ->where('user_id', $admin->id)
            ->where('action', 'Staff Two-Factor Challenge Passed')
            ->exists())->toBeTrue();
});

test('a valid trusted-browser cookie skips the next authenticator challenge', function () {
    [$admin, $secret] = configuredStaff();

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $challenge = $this->post(route('staff.two-factor.challenge.verify'), [
        'code' => (new Google2FA)->getCurrentOtp($secret),
        'trust_device' => '1',
    ]);

    $trustedCookie = $challenge->getCookie(StaffTwoFactorService::TRUST_COOKIE);
    expect($trustedCookie)->not->toBeNull();

    $this->post('/logout');

    $response = $this
        ->withCookie(StaffTwoFactorService::TRUST_COOKIE, $trustedCookie->getValue())
        ->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect('/admin/dashboard');
    expect(SystemLog::query()
        ->where('user_id', $admin->id)
        ->where('action', 'Trusted Device Used')
        ->exists())->toBeTrue();
});

test('staff can view and revoke their own trusted browsers', function () {
    [$admin] = configuredStaff();
    $device = TrustedDevice::create([
        'user_id' => $admin->id,
        'token_hash' => hash('sha256', 'profile-device-token'),
        'name' => 'Google Chrome on Windows',
        'ip_address' => '127.0.0.1',
        'last_used_at' => now(),
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Trusted browsers')
        ->assertSee('Google Chrome on Windows');

    $this->delete(route('staff.trusted-devices.revoke', $device), [
        'current_password' => 'password',
    ])->assertSessionHas('status', 'trusted-device-revoked');

    expect($device->refresh()->revoked_at)->not->toBeNull();
});

test('a recovery code is accepted only once', function () {
    [$technician, , $recoveryCode] = configuredStaff(['role' => 'technician']);

    $this->post('/login', [
        'email' => $technician->email,
        'password' => 'password',
    ]);

    $this->post(route('staff.two-factor.challenge.verify'), [
        'code' => $recoveryCode,
    ])->assertRedirect(route('technician.dashboard', absolute: false));

    $this->assertAuthenticatedAs($technician);
    expect($technician->refresh()->two_factor_recovery_codes)->toBe([]);

    $this->post('/logout');
    $this->post('/login', [
        'email' => $technician->email,
        'password' => 'password',
    ]);

    $this->post(route('staff.two-factor.challenge.verify'), [
        'code' => $recoveryCode,
    ])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('changing a staff password revokes all trusted browsers', function () {
    [$admin] = configuredStaff();
    $device = TrustedDevice::create([
        'user_id' => $admin->id,
        'token_hash' => hash('sha256', 'test-token'),
        'name' => 'Test browser',
        'expires_at' => now()->addDays(30),
    ]);

    $response = $this->actingAs($admin)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'New-Secure-Password-9482!',
        'password_confirmation' => 'New-Secure-Password-9482!',
    ]);

    $response->assertSessionHas('status', 'password-updated');
    expect($device->refresh()->revoked_at)->not->toBeNull();
});
