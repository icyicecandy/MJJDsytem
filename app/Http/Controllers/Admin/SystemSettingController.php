<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\ServicePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'servicePlans' => ServicePlan::query()->ordered()->get(),
            'businessSetting' => BusinessSetting::current(),
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $validated = $this->validatePlan($request, creating: true);
        $features = $this->featuresFromText($validated['features_text']);

        $plan = ServicePlan::query()->create([
            ...$this->planPayload($validated, $features),
            'code' => strtoupper($validated['code']),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        system_log('Service Plan Created', $plan->code.' was added to the service plan catalog.');

        return redirect()->to(route('admin.settings.index').'#service-plans')
            ->with('success', $plan->displayName().' was added successfully.');
    }

    public function updatePlan(Request $request, ServicePlan $servicePlan): RedirectResponse
    {
        $validated = $this->validatePlan($request);

        $servicePlan->fill([
            ...$this->planPayload($validated, $this->featuresFromText($validated['features_text'])),
            'is_active' => $servicePlan->is_active,
            'updated_by' => $request->user()->id,
        ])->save();

        $changed = collect(array_keys($servicePlan->getChanges()))
            ->reject(fn (string $field) => in_array($field, ['updated_at', 'updated_by'], true))
            ->implode(', ');

        system_log(
            'Service Plan Updated',
            $servicePlan->code.' was updated'.($changed ? ' ('.$changed.').' : '.').' Historical applications retained their submitted plan snapshot.'
        );

        return redirect()->to(route('admin.settings.index', ['plan' => $servicePlan->id]).'#service-plans')
            ->with('success', $servicePlan->displayName().' was updated successfully.');
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'support_email' => ['required', 'email', 'max:255'],
            'office_address' => ['required', 'string', 'max:500'],
            'business_hours' => ['required', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'map_url' => ['nullable', 'url:http,https', 'max:255'],
        ], [
            'phone.regex' => 'Enter a valid business phone number.',
        ]);

        $setting = BusinessSetting::current();
        $setting->fill($validated + ['updated_by' => $request->user()->id])->save();

        $changed = collect(array_keys($setting->getChanges()))
            ->reject(fn (string $field) => in_array($field, ['updated_at', 'updated_by'], true))
            ->implode(', ');
        system_log('Business Information Updated', 'Public business information was updated'.($changed ? ' ('.$changed.').' : '.'));

        return redirect()->to(route('admin.settings.index').'#business-information')
            ->with('success', 'Business information was updated successfully.');
    }

    public function updatePlanAvailability(Request $request, ServicePlan $servicePlan): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $activate = (bool) $validated['is_active'];

        $servicePlan = DB::transaction(function () use ($servicePlan, $activate) {
            $activePlans = ServicePlan::query()->active()->lockForUpdate()->get();
            $lockedPlan = ServicePlan::query()->lockForUpdate()->findOrFail($servicePlan->id);

            if (! $activate && $lockedPlan->is_active) {
                if ($activePlans->count() <= 1) {
                    throw ValidationException::withMessages([
                        'is_active' => 'At least one service plan must remain active for customer applications.',
                    ]);
                }
            }

            $lockedPlan->forceFill([
                'is_active' => $activate,
                'updated_by' => auth()->id(),
            ])->save();

            return $lockedPlan;
        });

        $action = $activate ? 'Service Plan Activated' : 'Service Plan Deactivated';
        system_log($action, $servicePlan->code.' was '.($activate ? 'made available for new applications.' : 'removed from new applications. Historical records were preserved.'));

        return redirect()->to(route('admin.settings.index', ['plan' => $servicePlan->id]).'#service-plans')
            ->with('success', $servicePlan->displayName().' is now '.($activate ? 'active.' : 'inactive.'));
    }

    private function validatePlan(Request $request, bool $creating = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'speed_mbps' => ['required', 'integer', 'min:1', 'max:10000'],
            'installation_fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'description' => ['required', 'string', 'max:500'],
            'features_text' => ['required', 'string', 'max:2000'],
            'badge' => ['nullable', 'string', 'max:40'],
            'is_featured' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];

        if ($creating) {
            $rules['code'] = ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_\-]+$/', Rule::unique('service_plans', 'code')];
        }

        return $request->validate($rules, [
            'code.regex' => 'Use uppercase letters, numbers, hyphens, or underscores for the plan code.',
            'features_text.required' => 'Add at least one customer-facing plan feature.',
        ]);
    }

    private function planPayload(array $validated, array $features): array
    {
        return [
            'name' => trim($validated['name']),
            'speed_mbps' => $validated['speed_mbps'],
            'installation_fee' => $validated['installation_fee'],
            'description' => trim($validated['description']),
            'features' => $features,
            'badge' => filled($validated['badge'] ?? null) ? trim($validated['badge']) : null,
            'is_featured' => (bool) $validated['is_featured'],
            'is_active' => (bool) $validated['is_active'],
            'display_order' => $validated['display_order'],
        ];
    }

    private function featuresFromText(string $features): array
    {
        $items = collect(preg_split('/\R/', $features))
            ->map(fn (string $feature) => trim($feature))
            ->filter()
            ->unique()
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['features_text' => 'Add at least one customer-facing plan feature.']);
        }

        if ($items->count() > 10) {
            throw ValidationException::withMessages(['features_text' => 'Use no more than 10 plan features.']);
        }

        return $items->all();
    }
}
