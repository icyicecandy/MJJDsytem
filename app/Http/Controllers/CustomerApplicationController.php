<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationDraft;
use App\Models\ApplicationIdVerificationEvent;
use App\Models\IdentityDocumentClaim;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Models\ServicePlan;
use App\Models\User;
use App\Rules\ReadableApplicationIdImage;
use App\Services\TechnicianSchedule;
use App\Services\WorkflowTransaction;
use App\Support\ApplicationContact;
use App\Support\ApplicationIdentity;
use App\Support\ApplicationIdUpload;
use App\Support\AppNotificationSender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerApplicationController extends Controller
{
    private const RESCHEDULE_AVAILABILITY_DAYS = 30;

    private const SERVICE_BOUNDS = [
        'south' => 15.45,
        'west' => 119.55,
        'north' => 16.55,
        'east' => 121.05,
        'center' => [15.96, 120.34],
        'zoom' => 9,
    ];

    public function create()
    {
        $user = Auth::user();
        $application = $user->application;

        if ($this->hasActiveApplication($application)) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'You already have an active application (status: '.$application->status.').');
        }

        $mapBounds = self::SERVICE_BOUNDS;
        $addressCatalog = config('luzon_service_areas.regions', []);

        $draft = ApplicationDraft::query()->where('user_id', $user->id)->where('expires_at', '>', now())->first();

        $validIdTypes = ApplicationIdentity::types();
        $servicePlans = ServicePlan::query()->active()->ordered()->get();

        return view('customer.apply', compact('application', 'mapBounds', 'addressCatalog', 'draft', 'validIdTypes', 'servicePlans'));
    }

    public function store(Request $request)
    {
        $obsoleteDraftPhotos = [];
        $response = WorkflowTransaction::customerApplication(auth()->user(), function (User $user) use ($request, &$obsoleteDraftPhotos) {
            $obsoleteDraftPhotos = [];

            return $this->storeWithinTransaction($request, $user, $obsoleteDraftPhotos);
        }, protectContacts: true);
        foreach ($obsoleteDraftPhotos as $obsoleteDraftPhoto) {
            ApplicationDraft::deletePhoto($obsoleteDraftPhoto);
        }

        return $response;
    }

    private function storeWithinTransaction(Request $request, User $user, array &$obsoleteDraftPhotos)
    {
        $existing = $user->application;
        $ownerUserId = $user->id;

        if ($this->hasActiveApplication($existing)) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'You already have an active application.');
        }

        $draft = ApplicationDraft::query()->where('user_id', $user->id)->where('expires_at', '>', now())->lockForUpdate()->first();
        if (($request->input('draft_token') ?: null) !== $draft?->token) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'draft' => 'Your saved draft changed in another tab or expired. Reload Apply and review it before submitting.',
            ]);
        }

        $request->merge([
            'email' => $this->normalizeEmailInput($request->input('email')),
            'mobile_number' => $this->normalizeContactNumberInput($request->input('mobile_number')),
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[\p{L}][\p{L}\p{M}\s\'.-]*$/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[\p{L}][\p{L}\p{M}\s\'.-]*$/u'],
            'email' => [
                'bail',
                'required',
                'string',
                'email',
                'max:255',
                ApplicationContact::uniqueRule('email', $ownerUserId),
            ],
            'mobile_number' => [
                'bail',
                'required',
                'string',
                'max:16',
                'regex:/^\+?[0-9]{10,15}$/',
                ApplicationContact::uniqueRule('mobile_number', $ownerUserId),
            ],
            'region' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street_landmark' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'plan_code' => [
                'required',
                Rule::exists('service_plans', 'code')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'valid_id_type' => ['required', Rule::in(array_keys(ApplicationIdentity::types()))],
            'valid_id_type_other' => [Rule::requiredIf($request->input('valid_id_type') === ApplicationIdentity::TYPE_OTHER), 'nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N}\s().,&\-\/]*$/u'],
            'id_image' => [$draft?->hasIdImage() ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', new ReadableApplicationIdImage],
            'id_selfie_image' => [$draft?->hasPhoto() ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', new ReadableApplicationIdImage],
        ], [
            'first_name.regex' => 'First name may contain letters, spaces, apostrophes, periods, and hyphens.',
            'last_name.regex' => 'Last name may contain letters, spaces, apostrophes, periods, and hyphens.',
            'email.unique' => 'This email address is already used in another application.',
            'mobile_number.regex' => 'Enter a valid contact number with 10 to 15 digits.',
            'mobile_number.unique' => 'This mobile number is already used in another application.',
            'valid_id_type.required' => 'Select the type of government ID you are submitting.',
            'valid_id_type_other.required' => 'Enter the name of the other government-issued ID.',
            'valid_id_type_other.regex' => 'Enter a valid government ID name.',
            'id_image.required' => 'Please upload a clear close-up photo of the valid ID.',
            'id_selfie_image.required' => 'Please upload the photo of the customer holding the valid ID.',
        ]);

        if (! $this->addressSelectionIsValid($validated['region'], $validated['province'], $validated['municipality'], $validated['barangay'])) {
            return back()
                ->withErrors(['region' => 'Please choose a valid Pangasinan service address from the provided dropdowns.'])
                ->withInput();
        }

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];

        if (! $this->coordinatesAreWithinServiceArea($lat, $lng)) {
            return back()
                ->withErrors(['lat' => 'Please pin a valid service location within Pangasinan.'])
                ->withInput();
        }

        $servicePlan = ServicePlan::query()
            ->active()
            ->where('code', $validated['plan_code'])
            ->firstOrFail();

        $idImageHash = $request->hasFile('id_image')
            ? ApplicationIdentity::uploadedFileHash($request->file('id_image'))
            : ApplicationIdentity::storedFileHash($draft?->id_image_path);
        $idImagePath = $request->hasFile('id_image')
            ? ApplicationIdUpload::store($request->file('id_image'))
            : $draft->id_image_path;
        $idSelfiePath = $request->hasFile('id_selfie_image')
            ? ApplicationIdUpload::store($request->file('id_selfie_image'))
            : $draft->photo_path;
        $duplicateImage = $this->claimBelongsToAnotherUser('id_image', $idImageHash, $user);
        $consentedAt = $user->consented_at ?? now();

        $payload = [
            // A private placeholder satisfies the existing non-null unique column
            // until the insert assigns the numeric ID used by the public reference.
            'reference_no' => 'PENDING-'.Str::uuid(),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'mobile_number' => $validated['mobile_number'],
            'region' => $validated['region'],
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_landmark' => $validated['street_landmark'] ?? null,
            'lat' => $lat,
            'lng' => $lng,
            'plan_code' => $validated['plan_code'],
            'plan_id' => $servicePlan->id,
            'plan_name_snapshot' => $servicePlan->name,
            'plan_speed_mbps_snapshot' => $servicePlan->speed_mbps,
            'plan_installation_fee_snapshot' => $servicePlan->installation_fee,
            'plan_description_snapshot' => $servicePlan->description,
            'plan_features_snapshot' => $servicePlan->features,
            'id_image_path' => $idImagePath,
            'id_selfie_image_path' => $idSelfiePath,
            'valid_id_type' => $validated['valid_id_type'],
            'valid_id_type_other' => $validated['valid_id_type'] === ApplicationIdentity::TYPE_OTHER ? trim($validated['valid_id_type_other']) : null,
            'valid_id_number' => null,
            'valid_id_number_fingerprint' => null,
            'id_image_hash' => $idImageHash,
            'duplicate_id_number_detected' => false,
            'duplicate_id_image_detected' => $duplicateImage,
            'id_verification_status' => ApplicationIdentity::STATUS_PENDING,
            'id_verification_reason' => null,
            'id_verified_by' => null,
            'id_verified_at' => null,
            'agreed_to_terms' => (bool) $user->agreed_to_terms,
            'agreed_to_privacy' => (bool) $user->agreed_to_privacy,
            'consented_at' => $consentedAt,
            'terms_version' => $user->terms_version ?? config('legal.terms_version'),
            'privacy_version' => $user->privacy_version ?? config('legal.privacy_version'),
            'status' => 'submitted',
            'rejection_reason' => null,
            'cancellation_reason' => null,
            'serviceability_status' => null,
            'slot_availability_status' => null,
            'assessment_notes' => null,
            'assessed_by' => null,
            'assessed_at' => null,
        ];

        $application = Application::create($payload + [
            'user_id' => $user->id,
        ]);

        $application->forceFill([
            'reference_no' => $application->sequentialReference(),
        ])->save();

        ApplicationIdVerificationEvent::create([
            'application_id' => $application->id,
            'actor_id' => $user->id,
            'from_status' => null,
            'to_status' => ApplicationIdentity::STATUS_PENDING,
            'reason' => 'Identity documents submitted for administrator review.',
        ]);

        AppNotificationSender::sendToRole(
            'admin',
            'New customer application submitted',
            $application->first_name.' '.$application->last_name.' submitted application '.$application->reference_no.'.'.($application->hasPotentialIdentityDuplicate() ? ' Identity documents require duplicate review.' : ''),
            route('admin.applications.show', $application),
            'application_submitted',
            ['application_id' => $application->id, 'reference_no' => $application->reference_no],
            Auth::id()
        );

        system_log(
            'Application Submitted',
            'Application '.$application->reference_no.' was submitted by '.$user->name.'.'
        );

        if ($draft) {
            $obsoleteDraftPhotos = array_values(array_filter([
                $draft->photo_path !== $idSelfiePath ? $draft->photo_path : null,
                $draft->id_image_path !== $idImagePath ? $draft->id_image_path : null,
            ]));
            $draft->delete();
        }

        return redirect()->route('customer.apply.done', ['reference' => $application->reference_no]);
    }

    private function claimBelongsToAnotherUser(string $kind, ?string $fingerprint, User $user): bool
    {
        if (! $fingerprint) {
            return false;
        }

        $claim = IdentityDocumentClaim::firstOrCreate(
            ['kind' => $kind, 'fingerprint' => $fingerprint],
            ['owner_user_id' => $user->id]
        );

        return (int) $claim->owner_user_id !== (int) $user->id;
    }

    public function contactCheck(Request $request)
    {
        $user = Auth::user();
        $ownerUserId = $user->id;

        $request->merge([
            'email' => $this->normalizeEmailInput($request->input('email')),
            'mobile_number' => $this->normalizeContactNumberInput($request->input('mobile_number')),
        ]);

        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:16', 'regex:/^\+?[0-9]{10,15}$/'],
        ], [
            'mobile_number.regex' => 'Enter a valid contact number with 10 to 15 digits.',
        ]);

        $email = trim((string) ($validated['email'] ?? ''));
        $mobileNumber = trim((string) ($validated['mobile_number'] ?? ''));

        $emailExists = $email !== '' && ApplicationContact::duplicate('email', $email, $ownerUserId);
        $mobileExists = $mobileNumber !== '' && ApplicationContact::duplicate('mobile_number', $mobileNumber, $ownerUserId);

        return response()->json([
            'email' => [
                'duplicate' => $emailExists,
                'message' => $emailExists ? 'This email address is already used in another application.' : null,
            ],
            'mobile_number' => [
                'duplicate' => $mobileExists,
                'message' => $mobileExists ? 'This mobile number is already used in another application.' : null,
            ],
        ]);
    }

    public function done(Request $request)
    {
        $reference = $request->query('reference');

        return view('customer.apply_done', compact('reference'));
    }

    public function show()
    {
        $applications = Auth::user()->applications()->with('installation.logs.user')->latest()->get();
        $application = $applications->first();
        $rescheduleAvailability = $application?->installation
            ? $this->customerRescheduleAvailability($application->installation)
            : [];

        return view('customer.application', compact('application', 'applications', 'rescheduleAvailability'));
    }

    public function resubmitIdentity(Request $request)
    {
        $obsoleteIdentityPhotos = [];
        $response = WorkflowTransaction::customerApplication(auth()->user(), function (User $user) use ($request, &$obsoleteIdentityPhotos) {
            return $this->resubmitIdentityWithinTransaction($request, $user, $obsoleteIdentityPhotos);
        });

        foreach ($obsoleteIdentityPhotos as $obsoleteIdentityPhoto) {
            try {
                ApplicationIdUpload::delete($obsoleteIdentityPhoto);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }

    private function resubmitIdentityWithinTransaction(Request $request, User $user, array &$obsoleteIdentityPhotos)
    {
        $application = $user->application;

        if (! $application) {
            return back()->withErrors(['identity_resubmission' => 'No application is available for ID resubmission.']);
        }

        if ($application->id_verification_status !== ApplicationIdentity::STATUS_REJECTED) {
            return back()->withErrors(['identity_resubmission' => 'ID documents can only be replaced after an administrator requests resubmission.']);
        }

        if (! in_array($application->status, [Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW], true)) {
            return back()->withErrors(['identity_resubmission' => 'This application is no longer accepting replacement ID documents.']);
        }

        $validated = $request->validate([
            'valid_id_type' => ['required', Rule::in(array_keys(ApplicationIdentity::types()))],
            'valid_id_type_other' => [Rule::requiredIf($request->input('valid_id_type') === ApplicationIdentity::TYPE_OTHER), 'nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N}\s().,&\-\/]*$/u'],
            'id_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', new ReadableApplicationIdImage],
            'id_selfie_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', new ReadableApplicationIdImage],
        ], [
            'valid_id_type.required' => 'Select the type of government ID you are replacing.',
            'valid_id_type_other.required' => 'Enter the name of the other government-issued ID.',
            'valid_id_type_other.regex' => 'Enter a valid government ID name.',
            'id_image.required' => 'Upload a new clear close-up photo of the ID.',
            'id_selfie_image.required' => 'Upload a new photo of yourself holding the same ID.',
        ]);

        $idImageHash = ApplicationIdentity::uploadedFileHash($request->file('id_image'));
        $idImagePath = ApplicationIdUpload::store($request->file('id_image'));
        $idSelfiePath = ApplicationIdUpload::store($request->file('id_selfie_image'));
        $duplicateImage = $this->claimBelongsToAnotherUser('id_image', $idImageHash, $user);
        $previousStatus = $application->id_verification_status;

        $obsoleteIdentityPhotos = array_values(array_filter([
            $application->id_image_path,
            $application->id_selfie_image_path,
        ]));

        $application->update([
            'id_image_path' => $idImagePath,
            'id_selfie_image_path' => $idSelfiePath,
            'valid_id_type' => $validated['valid_id_type'],
            'valid_id_type_other' => $validated['valid_id_type'] === ApplicationIdentity::TYPE_OTHER
                ? trim((string) $validated['valid_id_type_other'])
                : null,
            'valid_id_number' => null,
            'valid_id_number_fingerprint' => null,
            'id_image_hash' => $idImageHash,
            'duplicate_id_number_detected' => false,
            'duplicate_id_image_detected' => $duplicateImage,
            'id_verification_status' => ApplicationIdentity::STATUS_PENDING,
            'id_verification_reason' => null,
            'id_verified_by' => null,
            'id_verified_at' => null,
        ]);

        ApplicationIdVerificationEvent::create([
            'application_id' => $application->id,
            'actor_id' => $user->id,
            'from_status' => $previousStatus,
            'to_status' => ApplicationIdentity::STATUS_PENDING,
            'reason' => 'Customer submitted replacement identity documents for administrator review.',
        ]);

        AppNotificationSender::sendToRole(
            'admin',
            'Replacement ID documents submitted',
            $application->reference_no.' has new identity documents ready for review.'.($duplicateImage ? ' The close-up image requires duplicate review.' : ''),
            route('admin.applications.show', $application),
            'application_identity_resubmitted',
            ['application_id' => $application->id, 'id_verification_status' => ApplicationIdentity::STATUS_PENDING],
            $user->id
        );

        system_log(
            'Identity Documents Resubmitted',
            'Customer submitted replacement identity documents for Application '.$application->reference_no.'.'
        );

        return back()->with('success', 'Your replacement ID documents were submitted and are pending administrator review.');
    }

    public function requestReschedule(Request $request)
    {
        return WorkflowTransaction::customerApplication(auth()->user(), function (User $user) use ($request) {
            return $this->requestRescheduleWithinTransaction($request, $user);
        });
    }

    private function requestRescheduleWithinTransaction(Request $request, User $user)
    {
        $validated = $request->validate([
            'reschedule_preferred_date' => ['required', 'date', 'after:today'],
            'reschedule_preferred_time' => ['required', Rule::in(Installation::AVAILABLE_TIME_SLOTS)],
            'reschedule_reason' => ['required', 'string', 'max:1000'],
        ], [
            'reschedule_preferred_date.required' => 'Please choose your preferred installation date.',
            'reschedule_preferred_time.required' => 'Please choose your preferred installation time.',
            'reschedule_reason.required' => 'Please provide a reason for the reschedule request.',
        ]);

        $application = $user->application;

        TechnicianSchedule::assertInstallationWindow(
            $validated['reschedule_preferred_date'],
            $validated['reschedule_preferred_time'],
            'reschedule_preferred_date',
            'reschedule_preferred_time'
        );

        if (! $application || ! $application->installation) {
            return back()
                ->withErrors(['reschedule' => 'No scheduled installation is available for reschedule request.'])
                ->withInput();
        }

        $installation = $application->installation;

        if ($application->status !== Application::STATUS_SCHEDULED || $installation->status !== Installation::STATUS_SCHEDULED) {
            return back()
                ->withErrors(['reschedule' => 'Only scheduled installations can be requested for reschedule.'])
                ->withInput();
        }

        if ($installation->hasPendingCustomerRescheduleRequest()) {
            return back()
                ->withErrors(['reschedule' => 'Your previous reschedule request is still waiting for admin review.'])
                ->withInput();
        }

        $scheduledStart = $installation->scheduledStartAt();

        if (! $scheduledStart) {
            return back()
                ->withErrors(['reschedule' => 'The current installation schedule is incomplete. Please contact support.'])
                ->withInput();
        }

        if ($scheduledStart->lte(now()->addDay())) {
            return back()
                ->withErrors(['reschedule' => 'Reschedule requests must be made more than 24 hours before the installation visit.'])
                ->withInput();
        }

        $currentTime = $installation->installation_time
            ? \Carbon\Carbon::parse($installation->installation_time)->format('H:i')
            : null;

        if ($installation->installation_date?->format('Y-m-d') === $validated['reschedule_preferred_date']
            && $currentTime === $validated['reschedule_preferred_time']) {
            return back()
                ->withErrors(['reschedule' => 'Please choose a different preferred date or time from your current schedule.'])
                ->withInput();
        }

        if (! $this->customerRescheduleSlotIsAvailable(
            $validated['reschedule_preferred_date'],
            $validated['reschedule_preferred_time'],
            $installation,
        )) {
            return back()
                ->withErrors(['reschedule_preferred_time' => 'That date and time is no longer available. Please choose another available slot.'])
                ->withInput();
        }

        $reason = trim($validated['reschedule_reason']);

        $installation->update([
            'reschedule_requested_at' => now(),
            'reschedule_preferred_date' => $validated['reschedule_preferred_date'],
            'reschedule_preferred_time' => $validated['reschedule_preferred_time'],
            'reschedule_request_reason' => $reason,
            'reschedule_reviewed_at' => null,
            'reschedule_rejection_reason' => null,
        ]);

        $preferredSchedule = \Carbon\Carbon::parse($validated['reschedule_preferred_date'])->format('F d, Y')
            .' at '
            .\Carbon\Carbon::parse($validated['reschedule_preferred_time'])->format('g:i A');

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => Auth::id(),
            'notes' => 'Customer requested reschedule. Preferred schedule: '.$preferredSchedule.'. Reason: '.$reason,
        ]);

        AppNotificationSender::sendToRole(
            'admin',
            'Customer requested installation reschedule',
            $application->reference_no.' requested '.$preferredSchedule.'.',
            route('admin.applications.show', $application),
            'installation_reschedule_requested',
            ['application_id' => $application->id, 'installation_id' => $installation->id],
            Auth::id()
        );

        system_log(
            'Installation Reschedule Requested',
            'Customer requested reschedule for Application '.$application->reference_no.' to '.$preferredSchedule.'.'
        );

        return back()->with('success', 'Your reschedule request was sent for admin review.');
    }

    public function requestCancellation(Request $request)
    {
        return WorkflowTransaction::customerApplication(auth()->user(), function (User $user) use ($request) {
            return $this->requestCancellationWithinTransaction($request, $user);
        });
    }

    private function requestCancellationWithinTransaction(Request $request, User $user)
    {
        $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ], [
            'cancellation_reason.required' => 'Please provide a reason before requesting cancellation.',
        ]);

        $application = $user->application;

        if (! $application) {
            return back()->withErrors([
                'cancellation' => 'No application is available for cancellation.',
            ]);
        }

        $reason = trim((string) $request->input('cancellation_reason', ''));
        $reasonSuffix = $reason !== '' ? ' Reason: '.$reason : '';

        if (in_array($application->status, [Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW], true)
            || ($application->status === Application::STATUS_APPROVED && ! $this->hasScheduledInstallation($application))) {
            $application->update([
                'status' => Application::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
            ]);

            AppNotificationSender::sendToRole(
                'admin',
                'Application cancelled by customer',
                $application->reference_no.' was cancelled before installation scheduling.',
                route('admin.applications.show', $application),
                'application_cancelled_by_customer',
                ['application_id' => $application->id],
                Auth::id()
            );

            system_log(
                'Application Cancelled',
                'Application '.$application->reference_no.' was cancelled by '.Auth::user()->name.'.'.$reasonSuffix
            );

            return back()->with('success', 'Your application has been cancelled.');
        }

        if ($application->status === Application::STATUS_SCHEDULED && $application->installation
            && in_array($application->installation->status, [
                Installation::STATUS_SCHEDULED,
                Installation::STATUS_ASSIGNED,
                Installation::STATUS_IN_PROGRESS,
                Installation::STATUS_RESCHEDULE_REQUIRED,
            ], true)) {
            $installation = $application->installation;
            $scheduledStart = $installation->scheduledStartAt();

            if ($installation->status === Installation::STATUS_SCHEDULED
                && $scheduledStart && $scheduledStart->lte(now()->addDay())) {
                return back()->withErrors([
                    'cancellation' => 'Scheduled cancellations must be requested more than 24 hours before the visit. If work has already started, use this form as a Stop Work request.',
                ]);
            }

            $application->update([
                'status' => Application::STATUS_CANCELLATION_REQUESTED,
                'cancellation_reason' => $reason,
                'cancellation_previous_status' => $application->status,
            ]);

            $installation->update([
                'cancellation_previous_status' => $installation->status,
                'stop_work_requested_at' => now(),
                'stop_work_reviewed_at' => null,
                'stop_work_reviewed_by' => null,
                'stop_work_admin_notes' => null,
            ]);

            InstallationLog::create([
                'installation_id' => $installation->id,
                'user_id' => Auth::id(),
                'notes' => 'Customer submitted a Stop Work / Cancellation Request.'.$reasonSuffix,
            ]);

            AppNotificationSender::sendToRole(
                'admin',
                'Stop Work / Cancellation Request',
                $application->reference_no.' is waiting for admin review.',
                route('admin.applications.show', $application),
                'installation_cancellation_requested',
                ['application_id' => $application->id, 'installation_id' => $installation->id],
                Auth::id()
            );

            if ($installation->technician) {
                AppNotificationSender::send(
                    $installation->technician,
                    'Stop Work request received',
                    $application->reference_no.' is paused while an admin reviews the customer request.',
                    route('technician.installations.show', $installation),
                    'installation_stop_work_requested',
                    ['application_id' => $application->id, 'installation_id' => $installation->id],
                    Auth::id()
                );
            }

            return back()->with('success', 'Your Stop Work / Cancellation Request was sent for admin review.');
        }

        return back()->withErrors([
            'cancellation' => 'This application cannot be cancelled from its current status.',
        ]);
    }

    private function hasActiveApplication(?Application $application): bool
    {
        return $application !== null && ! $application->isTerminalForNewApplication();
    }

    private function normalizeEmailInput(mixed $value): mixed
    {
        return ApplicationContact::email($value);
    }

    private function normalizeContactNumberInput(mixed $value): mixed
    {
        return ApplicationContact::phone($value);
    }

    private function hasScheduledInstallation(Application $application): bool
    {
        return $application->installation
            && $application->installation->status === Installation::STATUS_SCHEDULED;
    }

    private function customerRescheduleSlotIsAvailable(string $date, string $time, Installation $currentInstallation): bool
    {
        $availability = $this->customerRescheduleAvailability($currentInstallation);

        return collect($availability[$date]['slots'] ?? [])
            ->contains(fn (array $slot) => ($slot['value'] ?? null) === $time);
    }

    private function customerRescheduleAvailability(Installation $currentInstallation): array
    {
        $technicians = User::query()
            ->where('role', 'technician')
            ->whereNull('disabled_at')
            ->where(function ($query) {
                $query->whereNull('technician_status')
                    ->orWhere('technician_status', '!=', 'on_leave');
            })
            ->get();

        if ($technicians->isEmpty()) {
            return [];
        }

        $startDate = now()->addDay()->startOfDay();
        $endDate = $startDate->copy()->addDays(self::RESCHEDULE_AVAILABILITY_DAYS - 1);
        $currentDate = $currentInstallation->installation_date?->format('Y-m-d');
        $currentTime = $currentInstallation->installation_time
            ? Carbon::parse($currentInstallation->installation_time)->format('H:i')
            : null;

        $currentInstallation->loadMissing('application');

        return TechnicianSchedule::installationAvailabilityGrid(
            $technicians,
            $currentInstallation->application,
            $startDate,
            $endDate,
            $currentInstallation->id,
            $currentDate,
            $currentTime
        );
    }

    private function coordinatesAreWithinServiceArea(float $lat, float $lng): bool
    {
        return $lat >= self::SERVICE_BOUNDS['south']
            && $lat <= self::SERVICE_BOUNDS['north']
            && $lng >= self::SERVICE_BOUNDS['west']
            && $lng <= self::SERVICE_BOUNDS['east'];
    }

    private function addressSelectionIsValid(string $region, string $province, string $municipality, string $barangay): bool
    {
        foreach (config('luzon_service_areas.regions', []) as $regionItem) {
            if (($regionItem['name'] ?? null) !== $region) {
                continue;
            }

            foreach ($regionItem['provinces'] ?? [] as $provinceItem) {
                if (($provinceItem['name'] ?? null) !== $province) {
                    continue;
                }

                foreach ($provinceItem['municipalities'] ?? [] as $municipalityItem) {
                    if (($municipalityItem['name'] ?? null) !== $municipality) {
                        continue;
                    }

                    $barangayNames = collect($municipalityItem['barangays'] ?? [])
                        ->map(fn ($item) => is_array($item) ? ($item['name'] ?? null) : $item)
                        ->filter()
                        ->values()
                        ->all();

                    if (empty($barangayNames)) {
                        return trim($barangay) !== '';
                    }

                    return in_array($barangay, $barangayNames, true);
                }
            }
        }

        return false;
    }
}
