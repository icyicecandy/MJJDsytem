<?php

use App\Models\Application;
use App\Models\ApplicationIdVerificationEvent;
use App\Models\IdentityDocumentClaim;
use App\Models\User;
use App\Support\ApplicationIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function identityWorkflowPayload(User $customer, ?string $ocrCandidate = null, array $overrides = []): array
{
    $region = config('luzon_service_areas.regions')[0];
    $province = $region['provinces'][0];
    $municipality = collect($province['municipalities'])->first(fn ($area) => ! empty($area['barangays']));
    $barangay = $municipality['barangays'][0];
    $identity = validApplicationIdentity();

    if ($ocrCandidate !== null) {
        $identity['valid_id_number'] = $ocrCandidate;
    }

    return array_replace([
        'first_name' => 'Identity',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'mobile_number' => '0917'.str_pad((string) $customer->id, 7, '0', STR_PAD_LEFT),
        'region' => $region['name'],
        'province' => $province['name'],
        'municipality' => $municipality['name'],
        'barangay' => is_array($barangay) ? $barangay['name'] : $barangay,
        'lat' => 16.2,
        'lng' => 121.0,
        'plan_code' => 'S2S_50',
    ] + $identity, $overrides);
}

function submitIdentityApplication($test, User $customer, ?string $ocrCandidate = null, array $overrides = []): Application
{
    $test->actingAs($customer)
        ->post(route('customer.apply.store'), identityWorkflowPayload($customer, $ocrCandidate, $overrides))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    return $customer->fresh()->application;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('other government ID requires a description and preserves its declared label without a customer-entered number', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->post(route('customer.apply.store'), identityWorkflowPayload($customer, null, [
            'valid_id_type' => ApplicationIdentity::TYPE_OTHER,
            'valid_id_type_other' => '',
        ]))
        ->assertSessionHasErrors('valid_id_type_other');

    expect(Application::count())->toBe(0);

    $application = submitIdentityApplication($this, $customer, null, [
        'valid_id_type' => ApplicationIdentity::TYPE_OTHER,
        'valid_id_type_other' => 'Barangay-issued photo identification card',
    ]);

    expect($application->valid_id_type_other)->toBe('Barangay-issued photo identification card')
        ->and($application->validIdTypeLabel())->toContain('Barangay-issued photo identification card')
        ->and($application->valid_id_number)->toBeNull();
});

test('customer submits without an ID number and administrator verifies with the review checklist', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $application = submitIdentityApplication($this, $customer);
    $admin = User::factory()->create(['role' => 'admin']);

    expect(DB::table('applications')->where('id', $application->id)->value('valid_id_number'))->toBeNull()
        ->and($application->valid_id_number)->toBeNull()
        ->and($application->valid_id_number_fingerprint)->toBeNull()
        ->and($application->id_image_hash)->toHaveLength(64)
        ->and($application->id_verification_status)->toBe(ApplicationIdentity::STATUS_PENDING)
        ->and($application->duplicate_id_number_detected)->toBeFalse()
        ->and($application->duplicate_id_image_detected)->toBeFalse()
        ->and(IdentityDocumentClaim::where('owner_user_id', $customer->id)->count())->toBe(1);

    $this->actingAs($admin)
        ->get(route('admin.applications.show', $application))
        ->assertOk()
        ->assertSee('No ID-number encoding is required.')
        ->assertDontSee('name="confirmed_id_number"', false)
        ->assertSee('name="review_document_readable"', false)
        ->assertSee('name="review_name_matches"', false)
        ->assertSee('name="review_photo_matches"', false)
        ->assertSee('Application name')
        ->assertSee('A difference is a warning and does not reject the application automatically.');

    $this->patch(route('admin.applications.identity-verification', $application), [
        'id_verification_status' => ApplicationIdentity::STATUS_VERIFIED,
        'id_verification_reason' => '',
    ])->assertSessionHasErrors([
        'review_document_readable',
        'review_name_matches',
        'review_photo_matches',
    ]);

    $this->patch(route('admin.applications.identity-verification', $application), [
        'id_verification_status' => ApplicationIdentity::STATUS_VERIFIED,
        'review_document_readable' => '1',
        'review_name_matches' => '1',
        'review_photo_matches' => '1',
        'id_verification_reason' => '',
    ])->assertSessionHasNoErrors();

    $application->refresh();
    expect(DB::table('applications')->where('id', $application->id)->value('valid_id_number'))->toBeNull()
        ->and($application->valid_id_number)->toBeNull()
        ->and($application->valid_id_number_fingerprint)->toBeNull()
        ->and($application->id_verification_status)->toBe(ApplicationIdentity::STATUS_VERIFIED)
        ->and($application->duplicate_id_number_detected)->toBeFalse()
        ->and(IdentityDocumentClaim::where('owner_user_id', $customer->id)->count())->toBe(1);

    $events = ApplicationIdVerificationEvent::where('application_id', $application->id)->oldest()->get();
    expect($events)->toHaveCount(2)
        ->and($events->first()->actor_id)->toBe($customer->id)
        ->and($events->last()->actor_id)->toBe($admin->id)
        ->and($events->last()->reason)->toContain('identity photos');
});

test('obsolete or manipulated customer ID-number input is ignored', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $application = submitIdentityApplication($this, $customer, 'OCR-READ-1001');
    $admin = User::factory()->create(['role' => 'admin']);

    expect($application->valid_id_number)->toBeNull()
        ->and($application->valid_id_number_fingerprint)->toBeNull()
        ->and($application->duplicate_id_number_detected)->toBeFalse()
        ->and(IdentityDocumentClaim::where('kind', 'id_number')->count())->toBe(0);

    $this->actingAs($admin)
        ->get(route('admin.applications.show', $application))
        ->assertOk()
        ->assertDontSee('OCR-READ-1001')
        ->assertDontSee('name="confirmed_id_number"', false)
        ->assertSee('Exact image reuse check');
});

test('exact duplicate ID images warn across accounts and require an override reason', function () {
    $firstCustomer = User::factory()->create(['role' => 'customer']);
    $first = submitIdentityApplication($this, $firstCustomer);
    $admin = User::factory()->create(['role' => 'admin']);

    $secondCustomer = User::factory()->create(['role' => 'customer']);
    $second = submitIdentityApplication($this, $secondCustomer);

    expect($second->duplicate_id_image_detected)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.applications.identity-verification', $second), [
            'id_verification_status' => ApplicationIdentity::STATUS_VERIFIED,
            'review_document_readable' => '1',
            'review_name_matches' => '1',
            'review_photo_matches' => '1',
            'id_verification_reason' => '',
        ])
        ->assertSessionHasErrors('id_verification_reason');

    $this->patch(route('admin.applications.identity-verification', $second), [
        'id_verification_status' => ApplicationIdentity::STATUS_VERIFIED,
        'review_document_readable' => '1',
        'review_name_matches' => '1',
        'review_photo_matches' => '1',
        'id_verification_reason' => 'The customer presented the original document during a manual review.',
    ])->assertSessionHasNoErrors();

    $first->refresh();
    $second->refresh();

    expect($second->duplicate_id_number_detected)->toBeFalse()
        ->and($second->duplicate_id_image_detected)->toBeTrue()
        ->and($second->hasPotentialIdentityDuplicate())->toBeTrue()
        ->and($second->valid_id_number_fingerprint)->toBeNull()
        ->and($second->id_verification_status)->toBe(ApplicationIdentity::STATUS_VERIFIED)
        ->and($second->id_verification_reason)->toContain('manual review');

    $this->get(route('admin.applications.show', $second))
        ->assertOk()
        ->assertSee('exact close-up ID image previously appeared under another account')
        ->assertSee($first->reference_no);
});

test('administrator review requires rejection reasons records history and gates approval until verified', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $application = submitIdentityApplication($this, $customer);
    $admin = User::factory()->create(['role' => 'admin']);

    $decision = [
        'status' => Application::STATUS_APPROVED,
        'serviceability_status' => 'serviceable',
        'slot_availability_status' => 'available',
        'assessment_notes' => 'Address and capacity checked.',
    ];

    $this->actingAs($admin)
        ->patch(route('admin.applications.status', $application), $decision)
        ->assertSessionHasErrors('status');

    $this->patch(route('admin.applications.identity-verification', $application), [
        'id_verification_status' => ApplicationIdentity::STATUS_REJECTED,
        'id_verification_reason' => '',
    ])->assertSessionHasErrors('id_verification_reason');

    $this->patch(route('admin.applications.identity-verification', $application), [
        'id_verification_status' => ApplicationIdentity::STATUS_REJECTED,
        'id_verification_reason' => 'The submitted ID image is not readable.',
    ])->assertSessionHasNoErrors();

    $rejected = $application->fresh();
    expect($rejected->id_verification_status)->toBe(ApplicationIdentity::STATUS_REJECTED)
        ->and($rejected->id_verification_reason)->toContain('not readable')
        ->and($rejected->id_verified_by)->toBe($admin->id)
        ->and($rejected->id_verified_at)->not->toBeNull();

    $this->patch(route('admin.applications.identity-verification', $application), [
        'id_verification_status' => ApplicationIdentity::STATUS_VERIFIED,
        'review_document_readable' => '1',
        'review_name_matches' => '1',
        'review_photo_matches' => '1',
        'id_verification_reason' => 'A clearer document was reviewed with the customer.',
    ])->assertSessionHasNoErrors();

    $this->patch(route('admin.applications.status', $application), $decision)
        ->assertSessionHasNoErrors();

    $application->refresh();
    expect($application->status)->toBe(Application::STATUS_APPROVED)
        ->and($application->id_verification_status)->toBe(ApplicationIdentity::STATUS_VERIFIED)
        ->and($application->idVerifier->is($admin))->toBeTrue();

    $events = $application->idVerificationEvents()->get()->sortBy('id')->values();
    expect($events)->toHaveCount(3)
        ->and($events->pluck('to_status')->all())->toBe([
            ApplicationIdentity::STATUS_PENDING,
            ApplicationIdentity::STATUS_REJECTED,
            ApplicationIdentity::STATUS_VERIFIED,
        ])
        ->and($events->last()->actor_id)->toBe($admin->id);
});

test('customer can replace rejected identity documents without rejecting the service application', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $application = submitIdentityApplication($this, $customer);
    $admin = User::factory()->create(['role' => 'admin']);
    $oldIdPath = $application->id_image_path;
    $oldSelfiePath = $application->id_selfie_image_path;

    $this->actingAs($admin)
        ->patch(route('admin.applications.identity-verification', $application), [
            'id_verification_status' => ApplicationIdentity::STATUS_REJECTED,
            'id_verification_reason' => 'The close-up image is blurred and the applicant name is unreadable.',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($customer)
        ->get(route('customer.application.show'))
        ->assertOk()
        ->assertSee('ID resubmission required')
        ->assertSee('Your service application has not been rejected.')
        ->assertSee('The close-up image is blurred and the applicant name is unreadable.')
        ->assertSee('Submit replacement ID')
        ->assertSee('name="id_image"', false)
        ->assertSee('name="id_selfie_image"', false);

    $this->patch(route('customer.application.identity.resubmit'), [
        'valid_id_type' => 'passport',
        'id_image' => applicationIdentityPhoto('replacement-id.jpg', 'holding-id.jpg'),
        'id_selfie_image' => applicationIdentityPhoto('replacement-selfie.jpg', 'holding-id.jpg'),
    ])->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect();

    $application->refresh();

    expect($application->status)->toBe(Application::STATUS_SUBMITTED)
        ->and($application->id_verification_status)->toBe(ApplicationIdentity::STATUS_PENDING)
        ->and($application->id_verification_reason)->toBeNull()
        ->and($application->id_verified_by)->toBeNull()
        ->and($application->id_verified_at)->toBeNull()
        ->and($application->valid_id_type)->toBe('passport')
        ->and($application->id_image_path)->not->toBe($oldIdPath)
        ->and($application->id_selfie_image_path)->not->toBe($oldSelfiePath)
        ->and($application->idVerificationEvents()->count())->toBe(3);

    Storage::disk('local')->assertMissing($oldIdPath);
    Storage::disk('local')->assertMissing($oldSelfiePath);
    Storage::disk('local')->assertExists($application->id_image_path);
    Storage::disk('local')->assertExists($application->id_selfie_image_path);

    $latestEvent = $application->idVerificationEvents()->latest('id')->first();
    expect($latestEvent->actor_id)->toBe($customer->id)
        ->and($latestEvent->from_status)->toBe(ApplicationIdentity::STATUS_REJECTED)
        ->and($latestEvent->to_status)->toBe(ApplicationIdentity::STATUS_PENDING)
        ->and($latestEvent->reason)->toContain('replacement identity documents');
});

test('customer cannot replace identity documents unless administrator requested resubmission', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $application = submitIdentityApplication($this, $customer);
    $originalIdPath = $application->id_image_path;

    $this->actingAs($customer)
        ->patch(route('customer.application.identity.resubmit'), [
            'valid_id_type' => 'passport',
            'id_image' => applicationIdentityPhoto('replacement-id.jpg', 'holding-id.jpg'),
            'id_selfie_image' => applicationIdentityPhoto('replacement-selfie.jpg', 'holding-id.jpg'),
        ])
        ->assertSessionHasErrors('identity_resubmission');

    expect($application->fresh()->id_verification_status)->toBe(ApplicationIdentity::STATUS_PENDING)
        ->and($application->id_image_path)->toBe($originalIdPath);
});

test('legacy applications without a recorded ID type remain reviewable and can follow the old approval path', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $payload = identityWorkflowPayload($customer);
    unset(
        $payload['valid_id_type'],
        $payload['valid_id_number'],
        $payload['id_image'],
        $payload['id_selfie_image'],
    );

    $application = Application::create($payload + [
        'user_id' => $customer->id,
        'reference_no' => 'APP-LEGACY-ID-1001',
        'status' => Application::STATUS_SUBMITTED,
        'id_verification_status' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.applications.show', $application))
        ->assertOk()
        ->assertSee('Legacy application')
        ->assertSee('Not specified');

    $this->patch(route('admin.applications.status', $application), [
        'status' => Application::STATUS_APPROVED,
        'serviceability_status' => 'serviceable',
        'slot_availability_status' => 'available',
        'assessment_notes' => 'Legacy record reviewed under the prior workflow.',
    ])->assertSessionHasNoErrors();

    expect($application->fresh()->status)->toBe(Application::STATUS_APPROVED)
        ->and($application->id_verification_status)->toBeNull();
});
