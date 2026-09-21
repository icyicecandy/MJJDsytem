<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Services\WorkflowTransaction;
use App\Support\AppNotificationSender;
use App\Support\PublicUpload;
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
        $selectedStatus = $request->query('status');
        if (! in_array($selectedStatus, [Installation::STATUS_IN_PROGRESS, Installation::STATUS_ACTIVATED], true)) {
            $selectedStatus = null;
        }
        $selectedGroup = $request->query('group', 'scheduled');
        if (! in_array($selectedGroup, ['scheduled', 'completed'], true)) {
            $selectedGroup = 'scheduled';
        }

        $baseQuery = Installation::with(['application'])
            ->where('technician_id', $technicianId);

        $scheduledInstallations = (clone $baseQuery)
            ->whereIn('status', [
                Installation::STATUS_SCHEDULED,
                Installation::STATUS_RESCHEDULE_REQUIRED,
            ])
            ->orderByRaw("CASE WHEN status = 'scheduled' THEN 0 WHEN status = 'reschedule_required' THEN 1 ELSE 2 END")
            ->orderBy('installation_date')
            ->orderBy('installation_time')
            ->orderBy('id')
            ->get();

        $inProgressInstallations = (clone $baseQuery)
            ->where('status', Installation::STATUS_IN_PROGRESS)
            ->orderBy('installation_date')
            ->orderBy('installation_time')
            ->orderBy('id')
            ->get();

        $completedInstallations = (clone $baseQuery)
            ->where('status', Installation::STATUS_COMPLETED)
            ->latest('completed_at')
            ->latest('id')
            ->get();

        $activatedInstallations = (clone $baseQuery)
            ->where('status', Installation::STATUS_ACTIVATED)
            ->latest('activation_date')
            ->latest('completed_at')
            ->latest('id')
            ->get();

        return view('technician.installations.index', compact(
            'scheduledInstallations',
            'inProgressInstallations',
            'completedInstallations',
            'activatedInstallations',
            'selectedGroup',
            'selectedStatus'
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
        return WorkflowTransaction::installation($installation, function (Installation $installation) use ($request) {
            return $this->updateInstallationStatusWithinTransaction($request, $installation);
        });
    }

    private function updateInstallationStatusWithinTransaction(Request $request, Installation $installation)
    {
        abort_if($installation->technician_id !== auth()->id(), 403);

        if ($installation->application->status === Application::STATUS_CANCELLATION_REQUESTED) {
            return back()->withErrors([
                'status' => 'This installation is waiting for cancellation review. Please wait for the admin decision.',
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:in_progress,reschedule_required'],
            'technician_remarks' => ['required_if:status,reschedule_required', 'nullable', 'string', 'max:1000'],
        ]);

        if (! $installation->canTransitionTo($validated['status'])) {
            return back()->withErrors([
                'status' => 'This installation cannot be moved to that status from its current state.',
            ]);
        }

        $updateData = [
            'status' => $validated['status'],
        ];

        if ($validated['status'] === Installation::STATUS_RESCHEDULE_REQUIRED) {
            $updateData['technician_remarks'] = $validated['technician_remarks'];
        }

        $installation->update($updateData);

        if ($validated['status'] === Installation::STATUS_RESCHEDULE_REQUIRED) {
            InstallationLog::create([
                'installation_id' => $installation->id,
                'user_id' => auth()->id(),
                'notes' => 'Installation marked as reschedule required. Remarks: ' . $validated['technician_remarks'],
            ]);

            system_log(
                'Reschedule Required',
                'Installation ' . $installation->displayJobReference() . ' was marked as reschedule required by ' . auth()->user()->name . '.'
            );

            AppNotificationSender::sendToRole(
                'admin',
                'Installation requires reschedule',
                'Installation #' . $installation->id . ' was marked reschedule required by ' . auth()->user()->name . '.',
                route('admin.applications.show', $installation->application),
                'installation_reschedule_required',
                ['installation_id' => $installation->id, 'application_id' => $installation->application_id],
                auth()->id()
            );

            return back()->with('success', 'Installation marked as reschedule required.');
        }

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Installation status updated to in progress.',
        ]);

        system_log(
            'Installation Started',
            'Installation ' . $installation->displayJobReference() . ' was started by ' . auth()->user()->name . '.'
        );

        return back()->with('success', 'Installation started successfully.');
    }

    public function addStopWorkNotes(Request $request, Installation $installation)
    {
        return WorkflowTransaction::installation($installation, function (Installation $installation) use ($request) {
            abort_if($installation->technician_id !== auth()->id(), 403);

            if (! $installation->hasPendingStopWorkRequest()
                || $installation->application?->status !== Application::STATUS_CANCELLATION_REQUESTED) {
                return back()->withErrors(['stop_work_notes' => 'There is no pending Stop Work request for this installation.']);
            }

            $validated = $request->validate([
                'stop_work_technician_notes' => ['required', 'string', 'max:1000'],
            ]);

            $installation->update([
                'stop_work_technician_notes' => trim($validated['stop_work_technician_notes']),
            ]);

            InstallationLog::create([
                'installation_id' => $installation->id,
                'user_id' => auth()->id(),
                'notes' => 'Technician Stop Work notes: '.trim($validated['stop_work_technician_notes']),
            ]);

            AppNotificationSender::sendToRole(
                'admin',
                'Technician added Stop Work notes',
                $installation->displayJobReference().' now has technician notes for the pending cancellation review.',
                route('admin.applications.show', $installation->application),
                'installation_stop_work_notes',
                ['application_id' => $installation->application_id, 'installation_id' => $installation->id],
                auth()->id()
            );

            return back()->with('success', 'Stop Work notes saved for admin review.');
        });
    }

    public function complete(Request $request, Installation $installation)
    {
        return WorkflowTransaction::installation($installation, function (Installation $installation) use ($request) {
            return $this->completeWithinTransaction($request, $installation);
        });
    }

    private function completeWithinTransaction(Request $request, Installation $installation)
    {
        abort_if($installation->technician_id !== auth()->id(), 403);

        if ($installation->application?->status === Application::STATUS_CANCELLATION_REQUESTED
            || $installation->hasPendingStopWorkRequest()) {
            return back()->withErrors([
                'status' => 'Completion is blocked while the Stop Work / Cancellation Request is waiting for admin review.',
            ]);
        }

        if (! $installation->canTransitionTo('completed')) {
            return back()->withErrors([
                'status' => 'Only in-progress installations can be marked as completed.',
            ]);
        }

        $validated = $request->validate([
            'technician_remarks' => ['required', 'string'],
            'nap_code' => ['required', 'string', 'max:100'],
            'port_number' => ['required', 'string', 'max:50'],
            'equipment_serial_number' => ['required', 'string', 'max:100'],
            'cable_length' => ['required', 'string', 'max:50'],
            'installation_photos' => ['required_without:installation_photo', 'array', 'min:1', 'max:10'],
            'installation_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'installation_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'installation_photos.required_without' => 'Upload at least one proof of installation photo.',
            'installation_photos.*.image' => 'Each proof of installation file must be an image.',
        ]);

        $updateData = [
            'status' => Installation::STATUS_COMPLETED,
            'completed_at' => now(),
            'technician_remarks' => $validated['technician_remarks'],
            'nap_code' => $validated['nap_code'],
            'port_number' => $validated['port_number'],
            'equipment_serial_number' => $validated['equipment_serial_number'],
            'cable_length' => $validated['cable_length'],
        ];

        $proofPaths = PublicUpload::storeFiles(
            PublicUpload::flattenFiles($request->file('installation_photos', []), $request->file('installation_photo')),
            'installation-photos'
        );

        if (! empty($proofPaths)) {
            $updateData['installation_photo_path'] = $proofPaths[0];
            $updateData['installation_photo_paths'] = $proofPaths;
        }

        $installation->update($updateData);

        $technicalNotes = sprintf(
            ' Technical details: NAP Code: %s; Port No.: %s; Equipment Serial No.: %s; Cable Length: %s.',
            $validated['nap_code'],
            $validated['port_number'],
            $validated['equipment_serial_number'],
            $validated['cable_length'],
        );

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Installation marked completed. Remarks: ' . $validated['technician_remarks'] . $technicalNotes,
        ]);

        system_log(
            'Installation Completed',
            'Installation ' . $installation->displayJobReference() . ' was completed by ' . auth()->user()->name . '.'
        );

        if ($installation->application) {
            $installation->application->update([
                'status' => Application::STATUS_INSTALLED,
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

    public function activate(Installation $installation)
    {
        return WorkflowTransaction::installation($installation, function (Installation $installation) {
            return $this->activateWithinTransaction($installation);
        });
    }

    private function activateWithinTransaction(Installation $installation)
    {
        abort_if($installation->technician_id !== auth()->id(), 403);

        if (! $installation->canTransitionTo(Installation::STATUS_ACTIVATED)) {
            return back()->withErrors([
                'status' => 'Only completed installations can be marked as activated.',
            ]);
        }

        $installation->update([
            'status' => Installation::STATUS_ACTIVATED,
            'activation_status' => Installation::STATUS_ACTIVATED,
            'activation_date' => now()->toDateString(),
        ]);

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Installation marked activated by technician.',
        ]);

        if ($installation->application) {
            $installation->application->update([
                'status' => Application::STATUS_ACTIVATED,
            ]);

            if ($installation->application->user) {
                AppNotificationSender::send(
                    $installation->application->user,
                    'Service activated',
                    'Your service has been activated. Open your application page to review the latest status.',
                    route('customer.application.show'),
                    'installation_activated',
                    ['installation_id' => $installation->id, 'application_id' => $installation->application->id],
                    auth()->id()
                );
            }

            AppNotificationSender::sendToRole(
                'admin',
                'Installation activated',
                'Installation #' . $installation->id . ' has been activated by ' . auth()->user()->name . '.',
                route('admin.applications.show', $installation->application),
                'installation_activated_admin',
                ['installation_id' => $installation->id, 'application_id' => $installation->application->id],
                auth()->id()
            );
        }

        return back()->with('success', 'Installation marked as activated.');
    }
}
