<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-23 10:00:00', config('app.timezone')));
});

afterEach(function () {
    $this->travelBack();
});

test('administrators can generate printable operations reports and export matching CSV data', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Report Administrator']);
    $customer = User::factory()->create(['role' => 'customer', 'name' => 'Report Customer']);
    Ticket::create([
        'user_id' => $customer->id,
        'subject' => 'No Internet Connection',
        'description' => 'Report fixture.',
        'status' => Ticket::STATUS_QUEUED,
        'urgency' => Ticket::URGENCY_HIGH,
        'urgency_review_required' => false,
        'response_due_at' => now()->addHours(4),
    ]);

    $query = ['type' => 'tickets', 'from' => '2026-09-01', 'to' => '2026-09-30', 'generate' => 1];
    $this->actingAs($admin)
        ->get(route('admin.reports.index', $query, false))
        ->assertOk()
        ->assertSee('Analytics &amp; Reports', false)
        ->assertSee('Reports')
        ->assertSee('Support Ticket Report')
        ->assertSee('Report Customer')
        ->assertSee('Print / Save as PDF')
        ->assertSee('Export CSV')
        ->assertSee('data-label="Tracking Number"', false);

    $csv = $this->actingAs($admin)->get(route('admin.reports.csv', $query, false));
    $csv->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('mjjd-tickets-2026-09-01-to-2026-09-30.csv');
    expect($csv->streamedContent())
        ->toContain('MJJD Telecom Tickets Report')
        ->toContain('Report Customer')
        ->toContain('No Internet Connection');
});

test('reports are admin only and support historical ranges spanning multiple years', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)
        ->get(route('admin.reports.index', absolute: false))
        ->assertForbidden();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'type' => 'operations',
            'from' => '2024-01-01',
            'to' => '2026-09-23',
        ], false))
        ->assertOk()
        ->assertSee('Jan 01, 2024')
        ->assertSee('Sep 23, 2026')
        ->assertSee('Historical reports can cover multiple years.');
});

test('invalid report date order returns to the form with a clear validation error', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('admin.reports.index', ['tab' => 'reports'], false))
        ->get(route('admin.reports.index', [
            'tab' => 'reports',
            'type' => 'applications',
            'from' => '2026-09-30',
            'to' => '2026-09-01',
            'generate' => 1,
        ], false))
        ->assertRedirect(route('admin.reports.index', ['tab' => 'reports'], false))
        ->assertSessionHasErrors('to');
});


test('analytics overview and customer reports respect the selected date range', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $included = User::factory()->create(['role' => 'customer', 'name' => 'Included Customer']);
    $included->forceFill(['created_at' => '2026-09-10 08:00:00'])->saveQuietly();
    $excluded = User::factory()->create(['role' => 'customer', 'name' => 'Excluded Customer']);
    $excluded->forceFill(['created_at' => '2026-08-10 08:00:00'])->saveQuietly();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'tab' => 'overview',
            'type' => 'operations',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ], false))
        ->assertOk()
        ->assertSee('Monthly Operations Trend')
        ->assertSee('Application Pipeline')
        ->assertSee('Recent Operations')
        ->assertSee('Customers registered');

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'tab' => 'reports',
            'type' => 'customers',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'generate' => 1,
        ], false))
        ->assertOk()
        ->assertSee('Customer Report')
        ->assertSee('Included Customer')
        ->assertDontSee('Excluded Customer');
});

test('recent operations use a compact mobile card layout without clipping desktop columns', function () {
    $css = file_get_contents(public_path('css/dashboard.css'));

    expect($css)
        ->toContain('@media screen and (max-width: 640px)')
        ->toContain('.analytics-recent-table thead {')
        ->toContain('grid-template-areas:')
        ->toContain('"type date"')
        ->toContain('content: attr(data-label);')
        ->toContain('.analytics-record-type {')
        ->toContain('white-space: nowrap;');
});

test('generated report records use labeled mobile cards for every report type', function () {
    $css = file_get_contents(public_path('css/dashboard.css'));

    expect($css)
        ->toContain('.report-table-section .report-table-wrap {')
        ->toContain('.report-table-section .report-data-table thead {')
        ->toContain('.report-table-section .report-data-table tbody tr {')
        ->toContain('grid-template-columns: minmax(6.6rem, 0.78fr) minmax(0, 1.22fr);')
        ->toContain('content: attr(data-label);');
});

test('administrators can print safe summaries for applications installations customers and technicians', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Print Administrator']);
    $customer = User::factory()->create([
        'role' => 'customer',
        'name' => 'Printable Customer',
        'phone' => '09171234567',
    ]);
    $technician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Printable Technician',
        'technician_status' => 'available',
        'technician_max_active_tickets' => 5,
    ]);
    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-PRINT',
        'first_name' => 'Printable',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bautista',
        'barangay' => 'Pogo',
        'street_landmark' => 'Near municipal hall',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SCHEDULED,
    ]);
    $installation = Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'installation_date' => '2026-09-28',
        'installation_time' => '08:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.print.application', $application, false))
        ->assertOk()->assertSee('Application Summary')->assertSee('APP-2026-PRINT')->assertDontSee('id_image_path');
    $this->actingAs($admin)
        ->get(route('admin.reports.print.installation', $installation, false))
        ->assertOk()->assertSee('Installation Summary')->assertSee('Printable Technician');
    $this->actingAs($admin)
        ->get(route('admin.reports.print.customer', $customer, false))
        ->assertOk()->assertSee('Customer Summary')->assertSee('Printable Customer');
    $this->actingAs($admin)
        ->get(route('admin.reports.print.technician', $technician, false))
        ->assertOk()->assertSee('Technician Summary')->assertSee('Printable Technician');
});


test('report builder requires a preview before print and export actions are shown', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'tab' => 'reports',
            'type' => 'applications',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ], false))
        ->assertOk()
        ->assertSee('Build a report')
        ->assertSee('Preview Report')
        ->assertSee('Choose the report you need')
        ->assertDontSee('Print / Save as PDF')
        ->assertDontSee('Export CSV');
});

test('generated reports present one print action and one export action', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'tab' => 'reports',
            'type' => 'applications',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'generate' => 1,
        ], false))
        ->assertOk();

    expect(substr_count($response->getContent(), 'Print / Save as PDF'))->toBe(1)
        ->and(substr_count($response->getContent(), 'Export CSV'))->toBe(1)
        ->and($response->getContent())->not->toContain('Ready to keep or share internally?');
});

test('report specific filters control the preview and are preserved in csv scope', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create(['role' => 'technician', 'name' => 'Scoped Technician']);
    $otherTechnician = User::factory()->create(['role' => 'technician', 'name' => 'Other Technician']);
    $customer = User::factory()->create(['role' => 'customer', 'name' => 'Scoped Customer']);

    Ticket::create([
        'user_id' => $customer->id,
        'assigned_staff_id' => $technician->id,
        'subject' => 'Included Scoped Ticket',
        'description' => 'Matches every report filter.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
        'urgency_review_required' => false,
        'response_due_at' => now()->addHours(4),
    ]);
    Ticket::create([
        'user_id' => $customer->id,
        'assigned_staff_id' => $technician->id,
        'subject' => 'Excluded Low Ticket',
        'description' => 'Wrong urgency.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_LOW,
        'urgency_review_required' => false,
        'response_due_at' => now()->addHours(8),
    ]);
    Ticket::create([
        'user_id' => $customer->id,
        'assigned_staff_id' => $otherTechnician->id,
        'subject' => 'Excluded Assignee Ticket',
        'description' => 'Wrong assignee.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
        'urgency_review_required' => false,
        'response_due_at' => now()->addHours(4),
    ]);

    $query = [
        'tab' => 'reports',
        'generate' => 1,
        'type' => 'tickets',
        'from' => '2026-09-01',
        'to' => '2026-09-30',
        'status' => 'open_queued',
        'technician' => $technician->id,
        'urgency' => Ticket::URGENCY_HIGH,
    ];

    $this->actingAs($admin)
        ->get(route('admin.reports.index', $query, false))
        ->assertOk()
        ->assertSee('Confirmed report scope')
        ->assertSee('1 matching record')
        ->assertSee('Included Scoped Ticket')
        ->assertSee('Scoped Technician')
        ->assertSee('Open / queued')
        ->assertDontSee('Excluded Low Ticket')
        ->assertDontSee('Excluded Assignee Ticket');

    $csv = $this->actingAs($admin)->get(route('admin.reports.csv', $query, false));
    $csv->assertOk();
    expect($csv->streamedContent())
        ->toContain('"Filter - Status","Open / queued"')
        ->toContain('"Filter - Assigned staff","Scoped Technician"')
        ->toContain('"Filter - Urgency",High')
        ->toContain('Included Scoped Ticket')
        ->not->toContain('Excluded Low Ticket')
        ->not->toContain('Excluded Assignee Ticket');
});

test('analytics compares the previous matching period and links metrics to scoped reports', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $current = User::factory()->create(['role' => 'customer', 'name' => 'Current Period Customer']);
    $current->forceFill(['created_at' => '2026-09-10 08:00:00'])->saveQuietly();
    $previous = User::factory()->create(['role' => 'customer', 'name' => 'Previous Period Customer']);
    $previous->forceFill(['created_at' => '2026-08-10 08:00:00'])->saveQuietly();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'tab' => 'overview',
            'type' => 'operations',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ], false))
        ->assertOk()
        ->assertSee('Compared with Aug 02, 2026 - Aug 31, 2026')
        ->assertSee('Select any metric to open its matching report.')
        ->assertSee('View matching customers registered report')
        ->assertSee('No change')
        ->assertSee('vs 1');
});


test('each operational report type can preview its supported filters', function (string $type, array $extra) {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', array_merge([
            'tab' => 'reports',
            'generate' => 1,
            'type' => $type,
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ], $extra), false))
        ->assertOk()
        ->assertSee('Confirmed report scope')
        ->assertSee('No records match this report scope.');
})->with([
    'applications' => ['applications', ['status' => 'pending_review']],
    'installations' => ['installations', ['status' => 'scheduled_assigned']],
    'technicians' => ['technicians', ['status' => 'active']],
]);
