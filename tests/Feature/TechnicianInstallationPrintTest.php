<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\User;

function createPrintableTechnicianInstallation(User $technician): Installation
{
    $customer = User::factory()->create(['role' => 'customer']);
    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-PRINT',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria.santos@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bautista',
        'barangay' => 'Pogo',
        'street_landmark' => 'Near municipal hall',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SCHEDULED,
    ]);

    return Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => '2026-10-29',
        'installation_time' => '11:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);
}

test('assigned technician can open and print their installation summary', function () {
    $technician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Assigned Technician',
    ]);
    $installation = createPrintableTechnicianInstallation($technician);

    $this->actingAs($technician)
        ->get(route('technician.installations.show', $installation, false))
        ->assertOk()
        ->assertSee('Print Summary')
        ->assertSee(route('technician.installations.print', $installation, false), false);

    $this->actingAs($technician)
        ->get(route('technician.installations.print', $installation, false))
        ->assertOk()
        ->assertSee('Installation Summary')
        ->assertSee('Print / Save as PDF')
        ->assertSee($installation->displayJobReference())
        ->assertSee('Assigned Technician')
        ->assertSee('Maria Santos')
        ->assertSee('Pogo, Bautista, Pangasinan, Ilocos Region')
        ->assertSee('October 29, 2026 at 11:00 AM');
});

test('technician cannot print an installation assigned to another technician', function () {
    $assignedTechnician = User::factory()->create(['role' => 'technician']);
    $otherTechnician = User::factory()->create(['role' => 'technician']);
    $installation = createPrintableTechnicianInstallation($assignedTechnician);

    $this->actingAs($otherTechnician)
        ->get(route('technician.installations.print', $installation, false))
        ->assertForbidden();
});