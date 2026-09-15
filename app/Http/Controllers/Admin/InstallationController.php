<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Models\User;
use App\Services\WorkflowTransaction;
use App\Support\AppNotificationSender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstallationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        if ($status && ! in_array($status, [
            Installation::STATUS_SCHEDULED,
            Installation::STATUS_IN_PROGRESS,
            Installation::STATUS_RESCHEDULE_REQUIRED,
            Installation::STATUS_COMPLETED,
            Installation::STATUS_ACTIVATED,
            Installation::STATUS_CANCELLED,
        ], true)) {
            $status = null;
        }
        $search = trim((string) $request->query('search', ''));
        $selectedGroup = $request->query('group', 'scheduled');
        if (! in_array($selectedGroup, ['scheduled', 'completed'], true)) {
            $selectedGroup = 'scheduled';
        }

        $query = Installation::query()
            ->with(['application', 'technician'])
            ->latest('installation_date')
            ->latest('installation_time')
            ->latest('id');

        if ($status) {
            $query->where('status', $status);
        } elseif ($selectedGroup === 'completed') {
            $query->where('status', Installation::STATUS_COMPLETED);
        } else {
            $query->whereIn('status', [
                Installation::STATUS_SCHEDULED,
                Installation::STATUS_RESCHEDULE_REQUIRED,
            ]);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhereHas('application', function ($applicationQuery) use ($search) {
                        $applicationQuery->where('id', $search)
                            ->orWhere('reference_no', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile_number', 'like', "%{$search}%")
                            ->orWhere('street_landmark', 'like', "%{$search}%")
                            ->orWhere('barangay', 'like', "%{$search}%")
                            ->orWhere('municipality', 'like', "%{$search}%")
                            ->orWhere('province', 'like', "%{$search}%")
                            ->orWhere('region', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                    })
                    ->orWhereHas('technician', function ($technicianQuery) use ($search) {
                        $technicianQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $scheduledCount = Installation::query()
            ->whereIn('status', [
                Installation::STATUS_SCHEDULED,
                Installation::STATUS_RESCHEDULE_REQUIRED,
            ])
            ->count();

        $inProgressCount = Installation::query()
            ->where('status', Installation::STATUS_IN_PROGRESS)
            ->count();

        $completedCount = Installation::query()
            ->where('status', Installation::STATUS_COMPLETED)
            ->count();

        $activatedCount = Installation::query()
            ->where('status', Installation::STATUS_ACTIVATED)
            ->count();

        $installations = $query->paginate(15)->withQueryString();

        return view('admin.installations.index', compact(
            'installations',
            'status',
            'search',
            'selectedGroup',
            'scheduledCount',
            'inProgressCount',
            'completedCount',
            'activatedCount'
        ));
    }

    public function reassign(Request $request, Installation $installation)
    {
        return WorkflowTransaction::installation($installation, function (Installation $installation) use ($request) {
            return $this->reassignWithinTransaction($request, $installation);
        }, $request->integer('technician_id') ?: null);
    }

    private function reassignWithinTransaction(Request $request, Installation $installation)
    {
        $validated = $request->validate([
            'technician_id' => ['required', 'exists:users,id'],
            'reassignment_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($installation->application->status === \App\Models\Application::STATUS_CANCELLATION_REQUESTED) {
            return back()->withErrors([
                'reassignment' => 'Review the pending cancellation request before reassigning this installation.',
            ]);
        }

        if ($installation->status !== Installation::STATUS_SCHEDULED) {
            return back()->withErrors([
                'reassignment' => 'Only scheduled installations can be reassigned.',
            ]);
        }

        $technician = User::find($validated['technician_id']);
        if (! $technician || ! $technician->isTechnician()) {
            return back()
                ->withErrors(['technician_id' => 'Selected user is not a technician.'])
                ->withInput();
        }

        if ((int) $installation->technician_id === (int) $technician->id) {
            return back()
                ->withErrors(['technician_id' => 'Choose a different technician for reassignment.'])
                ->withInput();
        }

        if ($installation->installation_date && $installation->installation_time && $this->technicianHasScheduleConflict(
            technicianId: (int) $technician->id,
            installationDate: $installation->installation_date->format('Y-m-d'),
            installationTime: Carbon::parse($installation->installation_time)->format('H:i'),
            ignoreInstallationId: $installation->id,
        )) {
            return back()
                ->withErrors(['technician_id' => 'This technician already has an installation scheduled during that time slot.'])
                ->withInput();
        }

        $oldTechnicianName = $installation->technician?->name ?? 'Unassigned';
        $reason = trim((string) ($validated['reassignment_reason'] ?? ''));

        $installation->update([
            'technician_id' => $technician->id,
            'assigned_at' => now(),
            'reassignment_reason' => $reason !== '' ? $reason : null,
            'status' => Installation::STATUS_SCHEDULED,
        ]);

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Technician reassigned due to unavailability. Previous technician: ' . $oldTechnicianName . '. New technician: ' . $technician->name . ($reason !== '' ? '. Reason: ' . $reason : ''),
        ]);

        system_log(
            'Reassigned Technician',
            'Reassigned ' . $installation->displayJobReference() . ' from ' . $oldTechnicianName . ' to ' . $technician->name . '.'
        );

        AppNotificationSender::send(
            $technician,
            'Installation reassigned to you',
            'You were assigned installation ' . $installation->displayJobReference() . '.',
            route('technician.installations.show', $installation),
            'installation_reassigned',
            ['installation_id' => $installation->id, 'application_id' => $installation->application_id],
            auth()->id()
        );

        if ($installation->application?->user) {
            AppNotificationSender::send(
                $installation->application->user,
                'Installation technician updated',
                'Your installation remains scheduled with an updated technician assignment.',
                route('customer.application.show'),
                'installation_reassigned_customer',
                ['installation_id' => $installation->id, 'application_id' => $installation->application_id],
                auth()->id()
            );
        }

        return back()->with('success', 'Technician reassigned. Installation remains scheduled.');
    }

    public function reschedule(Request $request, Installation $installation)
    {
        return WorkflowTransaction::installation($installation, function (Installation $installation) use ($request) {
            return $this->rescheduleWithinTransaction($request, $installation);
        }, $request->integer('technician_id') ?: null);
    }

    private function rescheduleWithinTransaction(Request $request, Installation $installation)
    {
        $validated = $request->validate([
            'technician_id' => ['required', 'exists:users,id'],
            'installation_date' => ['required', 'date'],
            'installation_time' => ['required', Rule::in(Installation::AVAILABLE_TIME_SLOTS)],
        ]);

        if ($installation->status !== Installation::STATUS_RESCHEDULE_REQUIRED) {
            return back()->withErrors([
                'reschedule' => 'Only installations marked as reschedule required can be rescheduled here.',
            ]);
        }

        $technician = User::find($validated['technician_id']);
        if (! $technician || ! $technician->isTechnician()) {
            return back()
                ->withErrors(['technician_id' => 'Selected user is not a technician.'])
                ->withInput();
        }

        if ($this->technicianHasScheduleConflict(
            technicianId: (int) $technician->id,
            installationDate: $validated['installation_date'],
            installationTime: $validated['installation_time'],
            ignoreInstallationId: $installation->id,
        )) {
            return back()
                ->withErrors(['installation_time' => 'This technician already has an installation scheduled during that time slot.'])
                ->withInput();
        }

        $technicianChanged = (int) $installation->technician_id !== (int) $technician->id;

        $installation->update([
            'technician_id' => $technician->id,
            'assigned_at' => $technicianChanged || ! $installation->assigned_at ? now() : $installation->assigned_at,
            'installation_date' => $validated['installation_date'],
            'installation_time' => $validated['installation_time'],
            'status' => Installation::STATUS_SCHEDULED,
        ]);

        if ($installation->application) {
            $installation->application->update(['status' => \App\Models\Application::STATUS_SCHEDULED]);
        }

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Installation rescheduled by admin for ' . $installation->installation_date?->format('F d, Y') . ' at ' . Carbon::parse($installation->installation_time)->format('g:i A') . '.',
        ]);

        system_log(
            'Installation Rescheduled',
            'Rescheduled ' . $installation->displayJobReference() . ' for ' . $installation->installation_date?->format('M d, Y') . ' at ' . Carbon::parse($installation->installation_time)->format('h:i A') . '.'
        );

        AppNotificationSender::send(
            $technician,
            'Installation rescheduled',
            'Installation ' . $installation->displayJobReference() . ' has a new schedule.',
            route('technician.installations.show', $installation),
            'installation_rescheduled',
            ['installation_id' => $installation->id, 'application_id' => $installation->application_id],
            auth()->id()
        );

        if ($installation->application?->user) {
            AppNotificationSender::send(
                $installation->application->user,
                'Installation rescheduled',
                'Your installation has been rescheduled. Open your application page to view the updated appointment.',
                route('customer.application.show'),
                'installation_rescheduled_customer',
                ['installation_id' => $installation->id, 'application_id' => $installation->application_id],
                auth()->id()
            );
        }

        return back()->with('success', 'Installation rescheduled successfully.');
    }

    private function technicianHasScheduleConflict(int $technicianId, string $installationDate, string $installationTime, ?int $ignoreInstallationId = null): bool
    {
        $newStart = Carbon::parse($installationDate . ' ' . $installationTime);
        $newEnd = $newStart->copy()->addMinutes(Installation::SLOT_DURATION_MINUTES);

        return Installation::query()
            ->where('technician_id', $technicianId)
            ->whereDate('installation_date', $installationDate)
            ->whereIn('status', Installation::activeSchedulingStatuses())
            ->whereNotNull('installation_time')
            ->when($ignoreInstallationId, fn ($query, $installationId) => $query->where('id', '!=', $installationId))
            ->lockForUpdate()
            ->get()
            ->contains(function (Installation $installation) use ($newStart, $newEnd) {
                $existingStart = $installation->scheduledStartAt();
                $existingEnd = $installation->scheduledEndAt();

                if (! $existingStart || ! $existingEnd) {
                    return false;
                }

                return $newStart->lt($existingEnd) && $newEnd->gt($existingStart);
            });
    }
}
