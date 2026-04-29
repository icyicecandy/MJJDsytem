<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Models\User;
use App\Support\AppNotificationSender;
use App\Support\PublicUpload;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                ->with('error', 'You already have an active application (status: ' . $application->status . ').');
        }

        $mapBounds = self::SERVICE_BOUNDS;
        $addressCatalog = config('luzon_service_areas.regions', []);

        return view('customer.apply', compact('application', 'mapBounds', 'addressCatalog'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $existing = $user->application;
        $existingId = $existing?->id;

        if ($this->hasActiveApplication($existing)) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'You already have an active application.');
        }

        $request->merge([
            'email' => $this->normalizeEmailInput($request->input('email')),
            'mobile_number' => $this->normalizeContactNumberInput($request->input('mobile_number')),
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z\s\'-]*$/'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z\s\'-]*$/'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('applications', 'email')->ignore($existingId),
            ],
            'mobile_number' => [
                'required',
                'string',
                'max:16',
                'regex:/^\+?[0-9]{10,15}$/',
                Rule::unique('applications', 'mobile_number')->ignore($existingId),
            ],
            'region' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street_landmark' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'plan_code' => ['required', 'in:S2S_50,S2S_100'],
            'id_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'id_selfie_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, and apostrophes.',
            'last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and apostrophes.',
            'email.unique' => 'This email address is already used in another application.',
            'mobile_number.regex' => 'Enter a valid contact number with 10 to 15 digits.',
            'mobile_number.unique' => 'This mobile number is already used in another application.',
            'id_image.required' => 'Please upload the valid ID photo on paper with 3 signatures.',
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

        $year = now()->format('Y');
        $random = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $reference = "APP-{$year}-{$random}";

        while (Application::where('reference_no', $reference)->exists()) {
            $random = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $reference = "APP-{$year}-{$random}";
        }

        $idPath = PublicUpload::normalizePath($request->file('id_image')->store('application-ids', 'public'));
        $idSelfiePath = PublicUpload::normalizePath($request->file('id_selfie_image')->store('application-ids', 'public'));
        $consentedAt = $user->consented_at ?? now();

        $payload = [
            'reference_no' => $reference,
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
            'id_image_path' => $idPath,
            'id_selfie_image_path' => $idSelfiePath,
            'agreed_to_terms' => (bool) $user->agreed_to_terms,
            'agreed_to_privacy' => (bool) $user->agreed_to_privacy,
            'consented_at' => $consentedAt,
            'terms_version' => $user->terms_version ?? config('legal.terms_version'),
            'privacy_version' => $user->privacy_version ?? config('legal.privacy_version'),
            'status' => 'submitted',
            'rejection_reason' => null,
            'serviceability_status' => null,
            'slot_availability_status' => null,
            'assessment_notes' => null,
            'assessed_by' => null,
            'assessed_at' => null,
        ];

        if ($existing && $existing->isTerminalForNewApplication()) {
            $existing->update($payload);
            $application = $existing;
        } else {
            $application = Application::create($payload + [
                'user_id' => Auth::id(),
            ]);
        }

        AppNotificationSender::sendToRole(
            'admin',
            'New customer application submitted',
            $application->first_name . ' ' . $application->last_name . ' submitted application ' . $application->reference_no . '.',
            route('admin.applications.show', $application),
            'application_submitted',
            ['application_id' => $application->id, 'reference_no' => $application->reference_no],
            Auth::id()
        );

        system_log(
            'Application Submitted',
            'Application ' . $application->reference_no . ' was submitted by ' . $user->name . '.'
        );

        return redirect()->route('customer.apply.done', ['reference' => $application->reference_no]);
    }

    public function contactCheck(Request $request)
    {
        $user = Auth::user();
        $existingId = $user->application?->id;

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

        $emailExists = $email !== '' && Application::query()
            ->where('email', $email)
            ->when($existingId, fn ($query) => $query->whereKeyNot($existingId))
            ->exists();

        $mobileExists = $mobileNumber !== '' && Application::query()
            ->where('mobile_number', $mobileNumber)
            ->when($existingId, fn ($query) => $query->whereKeyNot($existingId))
            ->exists();

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
        $application = Auth::user()->application()->with('installation.logs.user')->first();
        $rescheduleAvailability = $application?->installation
            ? $this->customerRescheduleAvailability($application->installation)
            : [];

        return view('customer.application', compact('application', 'rescheduleAvailability'));
    }

    public function requestReschedule(Request $request)
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

        $application = Auth::user()->application()->with('installation')->first();

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
            . ' at '
            . \Carbon\Carbon::parse($validated['reschedule_preferred_time'])->format('g:i A');

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => Auth::id(),
            'notes' => 'Customer requested reschedule. Preferred schedule: ' . $preferredSchedule . '. Reason: ' . $reason,
        ]);

        AppNotificationSender::sendToRole(
            'admin',
            'Customer requested installation reschedule',
            $application->reference_no . ' requested ' . $preferredSchedule . '.',
            route('admin.applications.show', $application),
            'installation_reschedule_requested',
            ['application_id' => $application->id, 'installation_id' => $installation->id],
            Auth::id()
        );

        system_log(
            'Installation Reschedule Requested',
            'Customer requested reschedule for Application ' . $application->reference_no . ' to ' . $preferredSchedule . '.'
        );

        return back()->with('success', 'Your reschedule request was sent for admin review.');
    }

    public function requestCancellation(Request $request)
    {
        $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $application = Auth::user()->application()->with('installation')->first();

        if (! $application) {
            return back()->withErrors([
                'cancellation' => 'No application is available for cancellation.',
            ]);
        }

        $reason = trim((string) $request->input('cancellation_reason', ''));
        $reasonSuffix = $reason !== '' ? ' Reason: ' . $reason : '';

        if (in_array($application->status, [Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW], true)
            || ($application->status === Application::STATUS_APPROVED && ! $this->hasScheduledInstallation($application))) {
            $application->update(['status' => Application::STATUS_CANCELLED]);

            AppNotificationSender::sendToRole(
                'admin',
                'Application cancelled by customer',
                $application->reference_no . ' was cancelled before installation scheduling.',
                route('admin.applications.show', $application),
                'application_cancelled_by_customer',
                ['application_id' => $application->id],
                Auth::id()
            );

            system_log(
                'Application Cancelled',
                'Application ' . $application->reference_no . ' was cancelled by ' . Auth::user()->name . '.'
            );

            return back()->with('success', 'Your application has been cancelled.');
        }

        if ($application->status === Application::STATUS_SCHEDULED && $this->hasScheduledInstallation($application)) {
            $installation = $application->installation;
            $scheduledStart = $installation->scheduledStartAt();

            if ($scheduledStart && $scheduledStart->lte(now()->addDay())) {
                return back()->withErrors([
                    'cancellation' => 'Cancellation requests for scheduled installations must be made more than 24 hours before the visit.',
                ]);
            }

            $application->update(['status' => Application::STATUS_CANCELLATION_REQUESTED]);

            InstallationLog::create([
                'installation_id' => $installation->id,
                'user_id' => Auth::id(),
                'notes' => 'Customer requested cancellation.' . $reasonSuffix,
            ]);

            AppNotificationSender::sendToRole(
                'admin',
                'Installation cancellation requested',
                $application->reference_no . ' is waiting for admin cancellation review.',
                route('admin.applications.show', $application),
                'installation_cancellation_requested',
                ['application_id' => $application->id, 'installation_id' => $installation->id],
                Auth::id()
            );

            return back()->with('success', 'Your cancellation request was sent for admin review.');
        }

        return back()->withErrors([
            'cancellation' => 'This application cannot be cancelled from its current status.',
        ]);
    }

    private function hasActiveApplication(?Application $application): bool
    {
        return $application !== null && ! $application->isTerminalForNewApplication();
    }

    private function normalizeEmailInput(mixed $value): string
    {
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', (string) $value) ?? '';

        return trim(preg_replace('/\s+/', '', $value) ?? $value);
    }

    private function normalizeContactNumberInput(mixed $value): string
    {
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', (string) $value) ?? '';

        return trim(preg_replace('/\s+/', '', $value) ?? $value);
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
        $technicianIds = User::query()
            ->where('role', 'technician')
            ->whereNull('disabled_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($technicianIds->isEmpty()) {
            return [];
        }

        $startDate = now()->addDay()->startOfDay();
        $endDate = $startDate->copy()->addDays(self::RESCHEDULE_AVAILABILITY_DAYS - 1);

        $activeSchedules = Installation::query()
            ->whereIn('status', Installation::activeSchedulingStatuses())
            ->whereIn('technician_id', $technicianIds)
            ->whereNotNull('installation_date')
            ->whereNotNull('installation_time')
            ->whereBetween('installation_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereKeyNot($currentInstallation->id)
            ->get(['id', 'technician_id', 'installation_date', 'installation_time']);
        $currentDateKey = $currentInstallation->installation_date?->format('Y-m-d');
        $currentTime = $currentInstallation->installation_time
            ? Carbon::parse($currentInstallation->installation_time)->format('H:i')
            : null;

        $availability = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->toDateString();
            $daySchedules = $activeSchedules
                ->filter(fn (Installation $schedule) => $schedule->installation_date?->format('Y-m-d') === $dateKey);

            $slots = collect(Installation::AVAILABLE_TIME_SLOTS)
                ->map(function (string $slot) use ($dateKey, $daySchedules, $technicianIds, $currentDateKey, $currentTime) {
                    if ($dateKey === $currentDateKey && $slot === $currentTime) {
                        return null;
                    }

                    $slotStart = Carbon::parse($dateKey . ' ' . $slot);
                    $slotEnd = $slotStart->copy()->addMinutes(Installation::SLOT_DURATION_MINUTES);

                    $takenTechnicians = $daySchedules
                        ->filter(function (Installation $schedule) use ($slotStart, $slotEnd) {
                            $existingStart = $schedule->scheduledStartAt();
                            $existingEnd = $schedule->scheduledEndAt();

                            if (! $existingStart || ! $existingEnd) {
                                return false;
                            }

                            return $slotStart->lt($existingEnd) && $slotEnd->gt($existingStart);
                        })
                        ->pluck('technician_id')
                        ->unique()
                        ->count();

                    $availableCount = $technicianIds->count() - $takenTechnicians;

                    if ($availableCount <= 0) {
                        return null;
                    }

                    return [
                        'value' => $slot,
                        'label' => Carbon::createFromFormat('H:i', $slot)->format('g:i A'),
                        'available_count' => $availableCount,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            if ($slots !== []) {
                $availability[$dateKey] = [
                    'label' => $date->format('F j, Y'),
                    'slots' => $slots,
                ];
            }
        }

        return $availability;
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
