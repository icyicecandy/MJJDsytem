<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\InstallationLog;
use App\Support\AppNotificationSender;
use Illuminate\Http\Request;

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

        $installation->load(['application', 'logs.user']);

        return view('technician.installations.show', compact('installation'));
    }

    public function updateInstallationStatus(Request $request, Installation $installation)
    {
        abort_if($installation->technician_id !== auth()->id(), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:in_progress'],
        ]);

        if (! $installation->canTransitionTo($validated['status'])) {
            return back()->withErrors([
                'status' => 'This installation cannot be moved to that status from its current state.',
            ]);
        }

        $installation->update([
            'status' => $validated['status'],
        ]);

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Installation status updated to in progress.',
        ]);

        return back()->with('success', 'Installation started successfully.');
    }

    public function complete(Request $request, Installation $installation)
    {
        abort_if($installation->technician_id !== auth()->id(), 403);

        if (! $installation->canTransitionTo('completed')) {
            return back()->withErrors([
                'status' => 'Only in-progress installations can be marked as completed.',
            ]);
        }

        $validated = $request->validate([
            'technician_remarks' => ['required', 'string'],
            'installation_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        $directory = 'installations/' . $installation->id;
        $updateData = [
            'status' => 'completed',
            'completed_at' => now(),
            'technician_remarks' => $validated['technician_remarks'],
        ];

        if ($request->hasFile('installation_photo')) {
            $updateData['installation_photo_path'] = $request->file('installation_photo')->store($directory, 'public');
        }

        $installation->update($updateData);

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Installation marked completed. Remarks: ' . $validated['technician_remarks'],
        ]);

        if ($installation->application) {
            $installation->application->update([
                'status' => 'installed',
            ]);

            if ($installation->application->user) {
                AppNotificationSender::send(
                    $installation->application->user,
                    'Installation completed',
                    'Your service installation has been completed. Open your application page to review the latest status.',
                    route('customer.application.show'),
                    'installation_completed',
                    ['installation_id' => $installation->id, 'application_id' => $installation->application->id],
                    auth()->id()
                );
            }

            AppNotificationSender::sendToRole(
                'admin',
                'Installation completed',
                'Installation #' . $installation->id . ' has been completed by ' . auth()->user()->name . '.',
                route('admin.installations.index'),
                'installation_completed_admin',
                ['installation_id' => $installation->id, 'application_id' => $installation->application->id],
                auth()->id()
            );
        }

        return back()->with('success', 'Installation marked as completed.');
    }
}
