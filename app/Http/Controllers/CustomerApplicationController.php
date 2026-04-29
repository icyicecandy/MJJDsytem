<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Support\AppNotificationSender;
use App\Support\PublicUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerApplicationController extends Controller
{
    private const LUZON_BOUNDS = [
        'south' => 11.8,
        'west' => 118.8,
        'north' => 19.9,
        'east' => 123.9,
        'center' => [16.2, 121.0],
        'zoom' => 7,
    ];

    public function create()
    {
        $user = Auth::user();
        $application = $user->application;

        if ($this->hasActiveApplication($application)) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'You already have an active application (status: ' . $application->status . ').');
        }

        $mapBounds = self::LUZON_BOUNDS;
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

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z\s\'-]*$/'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z\s\'-]*$/'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('applications', 'email')->ignore($existingId),
            ],
            'mobile_number' => [
                'required',
                'string',
                'max:30',
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
        ], [
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, and apostrophes.',
            'last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and apostrophes.',
            'email.unique' => 'This email address is already used in another application.',
            'mobile_number.unique' => 'This mobile number is already used in another application.',
            'id_image.required' => 'Please upload a valid ID image before submitting your application.',
        ]);

        if (! $this->addressSelectionIsValid($validated['region'], $validated['province'], $validated['municipality'], $validated['barangay'])) {
            return back()
                ->withErrors(['region' => 'Please choose a valid Luzon service address from the provided dropdowns.'])
                ->withInput();
        }

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];

        if (! $this->coordinatesAreWithinLuzon($lat, $lng)) {
            return back()
                ->withErrors(['lat' => 'Please pin a valid service location within Luzon.'])
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

        return redirect()->route('customer.apply.done', ['reference' => $application->reference_no]);
    }

    public function contactCheck(Request $request)
    {
        $user = Auth::user();
        $existingId = $user->application?->id;

        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:150'],
            'mobile_number' => ['nullable', 'string', 'max:30'],
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

        return view('customer.application', compact('application'));
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

    private function hasScheduledInstallation(Application $application): bool
    {
        return $application->installation
            && $application->installation->status === Installation::STATUS_SCHEDULED;
    }

    private function coordinatesAreWithinLuzon(float $lat, float $lng): bool
    {
        return $lat >= self::LUZON_BOUNDS['south']
            && $lat <= self::LUZON_BOUNDS['north']
            && $lng >= self::LUZON_BOUNDS['west']
            && $lng <= self::LUZON_BOUNDS['east'];
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
