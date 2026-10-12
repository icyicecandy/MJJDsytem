<?php

use App\Models\User;

test('administrator date filters keep their from and to controls in one grouped range', function (string $url, string $label, string $fromField, string $toField) {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get($url)
        ->assertOk()
        ->assertSee('class="filter-date-range', false)
        ->assertSee($label)
        ->assertSee('name="'.$fromField.'"', false)
        ->assertSee('name="'.$toField.'"', false);
})->with([
    'applications' => ['/admin/applications', 'Submitted date', 'date_from', 'date_to'],
    'customers' => ['/admin/customers', 'Registered date', 'date_from', 'date_to'],
    'installations' => ['/admin/installations', 'Scheduled date', 'date_from', 'date_to'],
    'tickets' => ['/admin/tickets', 'Submitted date', 'date_from', 'date_to'],
    'system activity' => ['/admin/system-logs', 'Occurred date', 'date_from', 'date_to'],
    'email delivery' => ['/admin/system-logs?view=email', 'Delivery date', 'date_from', 'date_to'],
    'reports' => ['/admin/reports?tab=reports', 'Report period', 'from', 'to'],
]);

test('shared date range styles keep paired dates together and stack them only on narrow screens', function () {
    $css = file_get_contents(public_path('css/dashboard.css'));

    expect($css)
        ->toContain('.filter-date-range__fields {')
        ->toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        ->toContain('.filter-date-range .system-log-field {')
        ->toContain('grid-column: auto;')
        ->toContain('@media (max-width: 520px) {')
        ->toContain('.filter-date-range__fields')
        ->toContain('grid-template-columns: minmax(0, 1fr);');
});

test('installation filter actions keep search and reset side by side', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->get('/admin/installations')
        ->assertOk()
        ->assertSee('>Search</button>', false)
        ->assertSee('>Reset</button>', false);

    expect($response->getContent())
        ->toContain('.installation-filter-toolbar .toolbar-actions {')
        ->toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        ->toContain('minmax(11.5rem, auto)');
});
