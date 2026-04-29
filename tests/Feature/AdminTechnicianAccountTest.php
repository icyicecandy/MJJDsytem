<?php

use App\Mail\TechnicianAccountCreatedMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

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
        ->patch(route('admin.technicians.disable', $technician, false), [
            'disabled_reason' => 'Repeated missed service appointments.',
        ]);

    $disableResponse
        ->assertRedirect(route('admin.technicians.show', $technician, false))
        ->assertSessionHas('status', 'technician-disabled');

    $technician->refresh();

    expect($technician->isDisabled())->toBeTrue()
        ->and($technician->disabled_by)->toBe($admin->id)
        ->and($technician->disabled_reason)->toBe('Repeated missed service appointments.')
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
        ->and($technician->disabled_reason)->toBeNull()
        ->and($technician->technician_status)->toBe('available');
});

test('admin must record a reason before disabling a technician account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create(['role' => 'technician']);

    $this
        ->actingAs($admin)
        ->from(route('admin.technicians.show', $technician, false))
        ->patch(route('admin.technicians.disable', $technician, false), [
            'disabled_reason' => '',
        ])
        ->assertRedirect(route('admin.technicians.show', $technician, false))
        ->assertSessionHasErrors('disabled_reason');

    expect($technician->refresh()->isDisabled())->toBeFalse()
        ->and($technician->disabled_reason)->toBeNull();
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
        ->assertSeeInOrder(['Assigned Tickets', 'Account Access', 'Back'])
        ->assertSee('Account Access')
        ->assertSee('Disable reason')
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

test('admin can create a technician account with a generated key', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $temporaryPassword = 'TempKey12345!';

    $this
        ->actingAs($admin)
        ->get(route('admin.technicians.index', absolute: false))
        ->assertOk()
        ->assertSee('Add New')
        ->assertSee('data-open-technician-create', false)
        ->assertSee(route('admin.technicians.store', absolute: false), false)
        ->assertSee('Add Technician')
        ->assertSee('Generated Key')
        ->assertSee('Select 1 to 3 days')
        ->assertDontSee('value="sat"', false)
        ->assertDontSee('value="sun"', false)
        ->assertSee('Create Technician');

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.technicians.store', absolute: false), [
            'first_name' => 'Raffy',
            'last_name' => 'Dela Cruz',
            'email' => 'raffy.tech@example.com',
            'phone' => '09171234567',
            'address' => 'Nibalew Sur, Bautista, Pangasinan',
            'technician_employment_type' => 'full_time',
            'temporary_password' => $temporaryPassword,
        ]);

    $technician = User::where('email', 'raffy.tech@example.com')->first();

    expect($technician)->not->toBeNull()
        ->and($technician->name)->toBe('Raffy Dela Cruz')
        ->and($technician->role)->toBe('technician')
        ->and($technician->technician_id)->not->toBeNull()
        ->and($technician->phone)->toBe('09171234567')
        ->and($technician->address)->toBe('Nibalew Sur, Bautista, Pangasinan')
        ->and($technician->technician_shift)->toBe(User::TECHNICIAN_STANDARD_SHIFT)
        ->and($technician->technician_employment_type)->toBe('full_time')
        ->and($technician->technician_status)->toBe('available')
        ->and(Hash::check($temporaryPassword, $technician->password))->toBeTrue();

    $response
        ->assertRedirect(route('admin.technicians.show', $technician, false))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, $temporaryPassword));

    Mail::assertSent(TechnicianAccountCreatedMail::class, function (TechnicianAccountCreatedMail $mail) use ($technician, $temporaryPassword) {
        return $mail->hasTo($technician->email)
            && $mail->technician->is($technician)
            && $mail->temporaryPassword === $temporaryPassword;
    });
});

test('admin can create a part time technician with selected work days', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.technicians.store', absolute: false), [
            'first_name' => 'Mika',
            'last_name' => 'Reyes',
            'email' => 'mika.tech@example.com',
            'phone' => '09175551234',
            'address' => 'Bautista, Pangasinan',
            'technician_employment_type' => 'part_time',
            'part_time_days' => ['mon', 'wed', 'fri'],
            'temporary_password' => 'PartTime12345!',
        ]);

    $technician = User::where('email', 'mika.tech@example.com')->first();

    expect($technician)->not->toBeNull()
        ->and($technician->technician_employment_type)->toBe('part_time')
        ->and($technician->technician_shift)->toBe('8:00 AM - 5:00 PM, Mon / Wed / Fri');

    $response
        ->assertRedirect(route('admin.technicians.show', $technician, false))
        ->assertSessionHasNoErrors();

    Mail::assertSent(TechnicianAccountCreatedMail::class, function (TechnicianAccountCreatedMail $mail) use ($technician) {
        return $mail->hasTo($technician->email)
            && $mail->temporaryPassword === 'PartTime12345!';
    });
});

test('admin cannot create a technician schedule with weekend work days', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    foreach (['sat', 'sun'] as $day) {
        $email = "{$day}.tech@example.com";

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.technicians.store', absolute: false), [
                'first_name' => 'Santi',
                'last_name' => 'Ramos',
                'email' => $email,
                'phone' => '09176667777',
                'address' => 'Bautista, Pangasinan',
                'technician_employment_type' => 'part_time',
                'part_time_days' => [$day],
                'temporary_password' => 'NoWeekend123!',
            ]);

        $response->assertSessionHasErrors('part_time_days.0');

        expect(User::where('email', $email)->exists())->toBeFalse();
    }

    Mail::assertNothingSent();
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
        ->patch(route('admin.technicians.disable', $otherTechnician, false), [
            'disabled_reason' => 'Unauthorized attempt.',
        ])
        ->assertForbidden();

    expect($otherTechnician->refresh()->isDisabled())->toBeFalse();
});
