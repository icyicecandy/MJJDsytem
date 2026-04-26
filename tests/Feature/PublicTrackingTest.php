<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;

test('public application tracking form is accessible', function () {
    $this->get(route('track.form'))
        ->assertOk()
        ->assertSeeText('Application Lookup');
});

test('public application tracking returns matching application details', function () {
    $customer = User::factory()->create([
        'role' => 'customer',
        'email' => 'applicant@example.com',
    ]);
    $technician = User::factory()->create(['role' => 'technician']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-1234',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'applicant@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Region I',
        'province' => 'Ilocos Norte',
        'municipality' => 'Laoag City',
        'barangay' => 'Barangay 1',
        'street_landmark' => 'Near plaza',
        'lat' => 16.0,
        'lng' => 120.0,
        'plan_code' => 'S2S_50',
        'status' => 'scheduled',
    ]);

    Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'installation_date' => '2026-04-30',
        'installation_time' => '09:00',
        'status' => 'scheduled',
    ]);

    $this->post(route('track.search'), [
        'reference_no' => ' app-2026-1234 ',
        'contact' => 'applicant@example.com',
    ])
        ->assertOk()
        ->assertSeeText('Application Tracking Result')
        ->assertSeeText('APP-2026-1234')
        ->assertSeeText('S2S_50')
        ->assertSeeText('2026-04-30');
});

test('public application tracking rejects mismatched contact details', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-5678',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'owner@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Region I',
        'province' => 'Ilocos Norte',
        'municipality' => 'Laoag City',
        'barangay' => 'Barangay 1',
        'street_landmark' => 'Near plaza',
        'lat' => 16.0,
        'lng' => 120.0,
        'plan_code' => 'S2S_50',
        'status' => 'submitted',
    ]);

    $this->from(route('track.form'))
        ->post(route('track.search'), [
            'reference_no' => 'APP-2026-5678',
            'contact' => 'wrong@example.com',
        ])
        ->assertRedirect(route('track.form'))
        ->assertSessionHasErrors('reference_no');
});

test('public ticket tracking form is accessible', function () {
    $this->get(route('track.ticket.form'))
        ->assertOk()
        ->assertSeeText('Ticket Lookup');
});

test('public ticket tracking returns matching ticket details', function () {
    $customer = User::factory()->create([
        'role' => 'customer',
        'email' => 'ticket-owner@example.com',
    ]);

    $ticket = Ticket::create([
        'user_id' => $customer->id,
        'subject' => 'No connection',
        'description' => 'Internet connection is down.',
        'status' => Ticket::STATUS_OPEN,
    ]);

    $this->post(route('track.ticket.search'), [
        'reference_no' => ' ' . strtolower($ticket->publicReference()) . ' ',
        'contact' => 'ticket-owner@example.com',
    ])
        ->assertOk()
        ->assertSeeText('Ticket Tracking Result')
        ->assertSeeText($ticket->publicReference())
        ->assertSeeText('No connection');
});

test('public ticket tracking rejects mismatched email', function () {
    $customer = User::factory()->create([
        'role' => 'customer',
        'email' => 'ticket-owner@example.com',
    ]);

    $ticket = Ticket::create([
        'user_id' => $customer->id,
        'subject' => 'Slow speed',
        'description' => 'Connection is unstable.',
        'status' => Ticket::STATUS_OPEN,
    ]);

    $this->from(route('track.ticket.form'))
        ->post(route('track.ticket.search'), [
            'reference_no' => $ticket->publicReference(),
            'contact' => 'other@example.com',
        ])
        ->assertRedirect(route('track.ticket.form'))
        ->assertSessionHasErrors('reference_no');
});
