<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Support\AppNotificationSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if ($this->hasActiveApplication($existing)) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'You already have an active application.');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'region' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street_landmark' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'plan_code' => ['required', 'in:S2S_50,S2S_100'],
            'id_image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'agreed_to_terms' => ['accepted'],
            'agreed_to_privacy' => ['accepted'],
            'terms_version' => ['nullable', 'string', 'max:50'],
            'privacy_version' => ['nullable', 'string', 'max:50'],
        ], [
            'agreed_to_terms.accepted' => config('legal.validation_message'),
            'agreed_to_privacy.accepted' => config('legal.validation_message'),
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

        $idPath = $request->file('id_image')->store('applications/ids', 'public');
        $consentedAt = now();

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
            'agreed_to_terms' => true,
            'agreed_to_privacy' => true,
            'consented_at' => $consentedAt,
            'terms_version' => $validated['terms_version'] ?? config('legal.terms_version'),
            'privacy_version' => $validated['privacy_version'] ?? config('legal.privacy_version'),
            'status' => 'submitted',
            'rejection_reason' => null,
            'serviceability_status' => null,
            'slot_availability_status' => null,
            'assessment_notes' => null,
            'assessed_by' => null,
            'assessed_at' => null,
        ];

        if ($existing && $existing->status === 'rejected') {
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

    public function done(Request $request)
    {
        $reference = $request->query('reference');

        return view('customer.apply_done', compact('reference'));
    }

    public function show()
    {
        $application = Auth::user()->application;

        return view('customer.application', compact('application'));
    }

    private function hasActiveApplication(?Application $application): bool
    {
        return $application !== null && $application->status !== 'rejected';
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

                    return in_array($barangay, $barangayNames, true);
                }
            }
        }

        return false;
    }
}
