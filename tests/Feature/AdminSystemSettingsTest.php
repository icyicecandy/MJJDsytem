<?php

use App\Models\Application;
use App\Models\BusinessSetting;
use App\Models\ServicePlan;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('only administrators can open system settings', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('System Settings')
        ->assertSee('Service Plans')
        ->assertSee('Business Information')
        ->assertSee('data-settings-workspace', false)
        ->assertSee('data-settings-tab="plans"', false)
        ->assertSee('Landing page preview')
        ->assertSee('Deactivate Plan');

    $this->actingAs($customer)
        ->get(route('admin.settings.index'))
        ->assertForbidden();
});

test('administrator can deactivate and reactivate a plan without deleting its history', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $plan = ServicePlan::query()->where('code', 'S2S_50')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.settings.plans.availability', $plan), ['is_active' => '0', 'editing_plan_id' => $plan->id])
        ->assertRedirect(route('admin.settings.index', ['plan' => $plan->id]).'#service-plans');

    expect($plan->refresh()->is_active)->toBeFalse()
        ->and(ServicePlan::query()->whereKey($plan->id)->exists())->toBeTrue()
        ->and(SystemLog::query()->where('action', 'Service Plan Deactivated')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.settings.plans.availability', $plan), ['is_active' => '1', 'editing_plan_id' => $plan->id])
        ->assertRedirect(route('admin.settings.index', ['plan' => $plan->id]).'#service-plans');

    expect($plan->refresh()->is_active)->toBeTrue()
        ->and(SystemLog::query()->where('action', 'Service Plan Activated')->exists())->toBeTrue();
});

test('plan changes update public displays and are audited', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $plan = ServicePlan::query()->where('code', 'S2S_50')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.settings.plans.update', $plan), [
            'name' => 'Superfiber Plus',
            'speed_mbps' => 75,
            'installation_fee' => 1250,
            'description' => 'Updated service plan for connected households.',
            'features_text' => "Up to 8 devices\nApplication review before installation",
            'badge' => 'Updated',
            'is_featured' => '1',
            'is_active' => '1',
            'display_order' => 5,
        ])
        ->assertRedirect(route('admin.settings.index', ['plan' => $plan->id]).'#service-plans');

    $this->post('/logout');
    $this->get('/')
        ->assertOk()
        ->assertSee('Superfiber Plus')
        ->assertSee('75 Mbps')
        ->assertSee('&#8369;1,250', false);

    expect(SystemLog::query()->where('action', 'Service Plan Updated')->exists())->toBeTrue();
});

test('administrator can add a validated plan to both customer entry points', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.settings.plans.store'), [
            'code' => 'S2S_150',
            'name' => 'Powerfiber',
            'speed_mbps' => 150,
            'installation_fee' => 2299,
            'description' => 'Faster access for larger households.',
            'features_text' => "Multiple-device connectivity\nApplication review before installation",
            'badge' => 'New',
            'is_featured' => '0',
            'is_active' => '1',
            'display_order' => 30,
        ])
        ->assertRedirect(route('admin.settings.index').'#service-plans');

    $plan = ServicePlan::query()->where('code', 'S2S_150')->firstOrFail();
    expect($plan->features)->toBe(['Multiple-device connectivity', 'Application review before installation'])
        ->and(SystemLog::query()->where('action', 'Service Plan Created')->exists())->toBeTrue();

    $this->post('/logout');
    $this->get('/')->assertOk()->assertSee('Powerfiber')->assertSee('150 Mbps');

    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)
        ->get(route('customer.apply'))
        ->assertOk()
        ->assertSee('value="S2S_150"', false)
        ->assertSee('PHP 2,299 installation fee');
});

test('inactive plans disappear and are rejected by application validation', function () {
    Storage::fake('local');

    $plan = ServicePlan::query()->where('code', 'S2S_50')->firstOrFail();
    $plan->update(['is_active' => false]);

    $customer = User::factory()->create([
        'role' => 'customer',
        'agreed_to_terms' => true,
        'agreed_to_privacy' => true,
        'consented_at' => now(),
    ]);

    $this->actingAs($customer)
        ->get(route('customer.apply'))
        ->assertOk()
        ->assertDontSee('value="S2S_50"', false)
        ->assertSee('value="S2S_100"', false);

    $this->actingAs($customer)
        ->post(route('customer.apply.store'), applicationPayload(['plan_code' => 'S2S_50']))
        ->assertSessionHasErrors('plan_code');

    expect(Application::query()->where('user_id', $customer->id)->exists())->toBeFalse();
});

test('the final active service plan cannot be deactivated', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ServicePlan::query()->where('code', 'S2S_100')->update(['is_active' => false]);
    $plan = ServicePlan::query()->where('code', 'S2S_50')->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.settings.index'))
        ->patch(route('admin.settings.plans.availability', $plan), [
            'is_active' => '0',
            'editing_plan_id' => $plan->id,
        ])
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHasErrors('is_active');

    expect($plan->refresh()->is_active)->toBeTrue();
});

test('submitted applications keep a historical plan snapshot after later edits', function () {
    Storage::fake('local');

    $customer = User::factory()->create([
        'role' => 'customer',
        'agreed_to_terms' => true,
        'agreed_to_privacy' => true,
        'consented_at' => now(),
        'terms_version' => config('legal.terms_version'),
        'privacy_version' => config('legal.privacy_version'),
    ]);

    $this->actingAs($customer)
        ->post(route('customer.apply.store'), applicationPayload())
        ->assertSessionDoesntHaveErrors();

    $application = Application::query()->where('user_id', $customer->id)->firstOrFail();
    expect($application->plan_name_snapshot)->toBe('Superfiber')
        ->and((float) $application->plan_installation_fee_snapshot)->toBe(999.0)
        ->and($application->planDisplayName())->toBe('Superfiber 50 Mbps');

    ServicePlan::query()->where('code', 'S2S_50')->update([
        'name' => 'Renamed Plan',
        'speed_mbps' => 80,
        'installation_fee' => 1999,
    ]);

    $application->refresh();
    expect($application->plan_name_snapshot)->toBe('Superfiber')
        ->and((float) $application->plan_installation_fee_snapshot)->toBe(999.0)
        ->and($application->planDisplayName())->toBe('Superfiber 50 Mbps');
});

test('business information changes are published and audited', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patch(route('admin.settings.business.update'), [
            'business_name' => 'MJJD Telecom Services',
            'phone' => '+63 917 123 4567',
            'support_email' => 'help@mjjd.example',
            'office_address' => 'Bayambang, Pangasinan',
            'business_hours' => 'Monday to Saturday, 8:00 AM - 5:00 PM',
            'facebook_url' => 'https://facebook.com/mjjdtelecom',
            'map_url' => 'https://maps.google.com/?q=Bayambang',
        ])
        ->assertRedirect(route('admin.settings.index').'#business-information');

    $this->post('/logout');
    $this->get('/')
        ->assertOk()
        ->assertSee('+63 917 123 4567')
        ->assertSee('help@mjjd.example')
        ->assertSee('Monday to Saturday, 8:00 AM - 5:00 PM')
        ->assertSee('Bayambang, Pangasinan');

    expect(BusinessSetting::current()->business_name)->toBe('MJJD Telecom Services')
        ->and(SystemLog::query()->where('action', 'Business Information Updated')->exists())->toBeTrue();
});

function applicationPayload(array $overrides = []): array
{
    return array_replace([
        'first_name' => 'Plan',
        'last_name' => 'Snapshot',
        'email' => 'plan.snapshot@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Agno',
        'barangay' => 'Allabon',
        'street_landmark' => 'Near the municipal hall',
        'lat' => 16.2,
        'lng' => 120.0,
        'plan_code' => 'S2S_50',
        'valid_id_type' => 'national_id',
        'id_image' => applicationIdentityPhoto('plan-id.png'),
        'id_selfie_image' => applicationIdentityPhoto('plan-selfie.png'),
    ], $overrides);
}
