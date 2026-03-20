<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TechnicianDashboardController extends Controller
{
    public function index()
    {
        return $this->installations(request());
    }

    public function installations(Request $request)
    {
        $technicianId = auth()->id();
        $selectedGroup = $request->query('group', 'scheduled');
        if (! in_array($selectedGroup, ['scheduled', 'completed'], true)) {
            $selectedGroup = 'scheduled';
        }

        $baseQuery = Installation::with(['application'])
            ->where('technician_id', $technicianId);

        $scheduledInstallations = (clone $baseQuery)
            ->where('status', '!=', 'completed')
            ->orderByRaw("CASE WHEN status = 'scheduled' THEN 0 ELSE 1 END")
            ->orderBy('installation_date')
            ->orderBy('installation_time')
            ->orderBy('id')
            ->get();

        $completedInstallations = (clone $baseQuery)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->latest('id')
            ->get();

        return view('technician.installations.index', compact(
            'scheduledInstallations',
            'completedInstallations',
            'selectedGroup'
        ));
    }

    public function showInstallation(Installation $installation)
    {
        abort_if($installation->technician_id !== auth()->id(), 403);

        $installation->load('application');

        return view('technician.installations.show', compact('installation'));
    }

    public function complete(Request $request, Installation $installation)
    {
        abort_if($installation->technician_id !== auth()->id(), 403);

        $validated = $request->validate([
            'nap_code' => ['required', 'string', 'max:255'],
            'lp_code' => ['nullable', 'string', 'max:255'],
            'np_code' => ['nullable', 'string', 'max:255'],
            'full_nap_label' => ['required', 'string', 'max:255'],
            'port_number' => ['required', 'string', 'max:100'],
            'jo_number' => ['required', 'string', 'max:100'],
            'activation_date' => ['required', 'date'],
            'activation_status' => ['required', 'string', 'max:100'],
            'package_name' => ['required', 'string', 'max:255'],
            'technician_remarks' => ['required', 'string'],
            'router_serial' => ['nullable', 'string', 'max:255'],
            'cable_reel_no' => ['nullable', 'string', 'max:255'],
            'material_notes' => ['nullable', 'string'],
            'speed_test_result' => ['nullable', 'string', 'max:255'],
            'router_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'speed_test_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'nap_box_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'acceptance_report_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'cable_reel_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'installation_site_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'customer_signature_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        $directory = 'installations/' . $installation->id;

        $installation->update([
            'status' => 'completed',
            'completed_at' => now(),
            'nap_code' => $validated['nap_code'],
            'lp_code' => $validated['lp_code'] ?? null,
            'np_code' => $validated['np_code'] ?? null,
            'full_nap_label' => $validated['full_nap_label'],
            'port_number' => $validated['port_number'],
            'jo_number' => $validated['jo_number'],
            'activation_date' => $validated['activation_date'],
            'activation_status' => $validated['activation_status'],
            'package_name' => $validated['package_name'],
            'technician_remarks' => $validated['technician_remarks'],
            'router_serial' => $validated['router_serial'] ?? null,
            'cable_reel_no' => $validated['cable_reel_no'] ?? null,
            'material_notes' => $validated['material_notes'] ?? null,
            'speed_test_result' => $validated['speed_test_result'] ?? null,
            'router_photo_path' => $request->file('router_photo')->store($directory, 'public'),
            'speedtest_photo_path' => $request->file('speed_test_photo')->store($directory, 'public'),
            'nap_photo_path' => $request->file('nap_box_photo')->store($directory, 'public'),
            'acceptance_report_photo_path' => $request->file('acceptance_report_photo')->store($directory, 'public'),
            'cable_reel_photo_path' => $request->file('cable_reel_photo')?->store($directory, 'public'),
            'installation_photo_path' => $request->file('installation_site_photo')?->store($directory, 'public'),
            'customer_signature_photo_path' => $request->file('customer_signature_photo')?->store($directory, 'public'),
        ]);

        if ($installation->application) {
            $installation->application->update([
                'status' => 'installed',
            ]);

            if (Schema::hasTable('subscriptions') && $installation->application->user_id) {
                Subscription::updateOrCreate(
                    ['user_id' => $installation->application->user_id],
                    [
                        'activated_at' => $validated['activation_date'],
                        'status' => strtolower($validated['activation_status']) === 'activated' ? 'active' : 'pending',
                    ]
                );
            }
        }

        return back()->with('success', 'Installation marked as completed with full field activation details.');
    }
}
