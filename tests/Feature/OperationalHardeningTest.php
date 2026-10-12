<?php

use App\Models\EmailDeliveryLog;
use App\Models\User;
use App\Mail\CustomerWorkflowUpdateMail;
use App\Support\TrackedEmailSender;

test('successful and failed email attempts are visible to administrators', function () {
    $successful = TrackedEmailSender::attempt(
        'customer@example.test',
        'Application approval',
        fn () => true,
        ['application_id' => 123]
    );

    $failed = TrackedEmailSender::attempt(
        'failed@example.test',
        'Customer email verification',
        fn () => throw new RuntimeException('Simulated transport failure')
    );

    expect($successful)->toBeTrue()->and($failed)->toBeFalse();
    $this->assertDatabaseHas('email_delivery_logs', [
        'recipient' => 'customer@example.test',
        'notification_type' => 'Application approval',
        'status' => 'sent',
    ]);
    $this->assertDatabaseHas('email_delivery_logs', [
        'recipient' => 'failed@example.test',
        'status' => 'failed',
        'retry_status' => 'available',
    ]);
    $this->assertDatabaseHas('system_logs', [
        'action' => 'Email Sent',
        'description' => 'Application approval email sent to customer@example.test.',
    ]);
    $this->assertDatabaseHas('system_logs', [
        'action' => 'Email Failed',
        'description' => 'Customer email verification email to failed@example.test could not be sent.',
    ]);

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route('admin.system-logs.index', ['view' => 'email', 'email_status' => 'failed']))
        ->assertOk()
        ->assertSee('Email Delivery')
        ->assertSee('failed@example.test')
        ->assertDontSee('customer@example.test');

    expect(EmailDeliveryLog::count())->toBe(2);
});

test('operational detail pages expose next action guidance and shared form protection', function () {
    $script = file_get_contents(public_path('js/form-protection.js'));
    $dashboardScript = file_get_contents(public_path('js/dashboard.js'));
    $draftScript = file_get_contents(public_path('js/application-draft.js'));
    $styles = file_get_contents(public_path('css/ui-system.css'));

    expect($script)
        ->toContain('data-protect-form')
        ->toContain('Saving...')
        ->toContain('beforeunload')
        ->toContain('role="alertdialog"')
        ->toContain('MJJDConfirm')
        ->not->toContain('window.confirm')
        ->and($dashboardScript)->not->toContain('window.confirm')
        ->and($draftScript)
        ->toContain('window.MJJDConfirm')
        ->not->toContain('window.confirm')
        ->and($styles)
        ->toContain('.next-action-card')
        ->toContain('.active-filter-summary')
        ->toContain('.ui-confirm-modal')
        ->toContain('.alert-dismiss');
});

test('customer workflow email presents a clear action and staff role without private contact details', function () {
    $mail = new CustomerWorkflowUpdateMail(
        'MJJD Telecom Ticket Assigned - TKT-2026-0099',
        'Support staff assigned',
        'Raffy Dela Cruz (Technician) is now handling your support ticket.',
        'TKT-2026-0099',
        'https://example.test/customer/tickets/99',
        'View support ticket',
        ['Assigned staff' => 'Raffy Dela Cruz (Technician)']
    );

    expect($mail->render())
        ->toContain('Support staff assigned')
        ->toContain('Raffy Dela Cruz (Technician)')
        ->toContain('View support ticket')
        ->not->toContain('raffy@example.test')
        ->not->toContain('0917');
});
