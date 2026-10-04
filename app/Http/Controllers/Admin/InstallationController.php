<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Services\TechnicianSchedule;
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
        $selectedGroup = $request->query('group', 'all');
        if (! in_array($selectedGroup, ['all', 'scheduled', 'completed'], true)) {
            $selectedGroup = 'all';
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
        } elseif ($selectedGroup === 'scheduled') {
            $query->where('status', Installation::STATUS_SCHEDULED);
        }

        if ($search !== '') {
            $jobId = preg_match('/^JOB-\d{4}-(\d+)$/i', $search, $jobMatch)
                ? (int) $jobMatch[1]
                : (ctype_digit($search) ? (int) $search : null);
            $normalizedStatus = preg_replace('/[^a-z0-9]+/', '_', strtolower($search));
            $normalizedStatus = $normalizedStatus === 'installed' ? Installation::STATUS_COMPLETED : $normalizedStatus;
            $nameParts = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
            $firstNameSearch = $nameParts[0] ?? null;
            $lastNameSearch = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null;

            $query->where(function ($q) use ($search, $jobId, $normalizedStatus, $firstNameSearch, $lastNameSearch) {
                if ($jobId !== null) {
                    $q->where('id', $jobId);
                } else {
                    $q->where('status', 'like', "%{$normalizedStatus}%");
                }

                $q->orWhereHas('application', function ($applicationQuery) use ($search, $firstNameSearch, $lastNameSearch) {
                    $applicationQuery->where('id', $search)
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('plan_code', 'like', "%{$search}%")
                        ->orWhere('street_landmark', 'like', "%{$search}%")
                        ->orWhere('barangay', 'like', "%{$search}%")
                        ->orWhere('municipality', 'like', "%{$search}%")
                        ->orWhere('province', 'like', "%{$search}%")
                        ->orWhere('region', 'like', "%{$search}%");

                    if ($firstNameSearch && $lastNameSearch) {
                        $applicationQuery->orWhere(function ($fullNameQuery) use ($firstNameSearch, $lastNameSearch) {
                            $fullNameQuery->where('first_name', 'like', "%{$firstNameSearch}%")
                                ->where('last_name', 'like', "%{$lastNameSearch}%");
                        });
                    }
                })
                    ->orWhereHas('technician', function ($technicianQuery) use ($search) {
                        $technicianQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $allCount = Installation::query()->count();

        $scheduledCount = Installation::query()
            ->where('status', Installation::STATUS_SCHEDULED)
            ->count();

        $rescheduleCount = Installation::query()
            ->where('status', Installation::STATUS_RESCHEDULE_REQUIRED)
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
            'allCount',
            'scheduledCount',
            'rescheduleCount',
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
            'technician_id' => ['required', 'integer', 'exists:users,id'],
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

        $technician = TechnicianSchedule::eligibleTechnician((int) $validated['technician_id']);

        if ((int) $installation->technician_id === (int) $technician->id) {
            return back()
                ->withErrors(['technician_id' => 'Choose a different technician for reassignment.'])
                ->withInput();
        }

        if (! $installation->scheduledStartAt()) {
            return back()->withErrors(['technician_id' => 'Set a future appointment before reassigning this installation.']);
        }
        TechnicianSchedule::assertInstallationAvailable(
            $technician,
            $installation->application,
            $installation->installation_date->format('Y-m-d'),
            Carbon::parse($installation->installation_time)->format('H:i'),
            $installation->id,
            'technician_id',
            'technician_id'
        );

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
            'notes' => 'Technician reassigned due to unavailability. Previous technician: '.$oldTechnicianName.'. New technician: '.$technician->name.($reason !== '' ? '. Reason: '.$reason : ''),
        ]);

        system_log(
            'Reassigned Technician',
            'Reassigned '.$installation->displayJobReference().' from '.$oldTechnicianName.' to '.$technician->name.'.'
        );

        AppNotificationSender::send(
            $technician,
            'Installation reassigned to you',
            'You were assigned installation '.$installation->displayJobReference().'.',
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
            'technician_id' => ['required', 'integer', 'exists:users,id'],
            'installation_date' => ['required', 'date_format:Y-m-d'],
            'installation_time' => ['required', Rule::in(Installation::AVAILABLE_TIME_SLOTS)],
            'customer_reschedule_reason' => ['required', 'string', 'max:1000'],
        ]);

        if (! $installation->canBeRescheduledByAdmin() || $installation->application?->status === \App\Models\Application::STATUS_CANCELLATION_REQUESTED) {
            return back()->withErrors([
                'reschedule' => 'Only overdue scheduled installations or installations marked as reschedule required can be rescheduled here. Review any pending cancellation first.',
            ]);
        }

        $technician = TechnicianSchedule::eligibleTechnician((int) $validated['technician_id']);

        TechnicianSchedule::assertInstallationAvailable(
            $technician,
            $installation->application,
            $validated['installation_date'],
            $validated['installation_time'],
            $installation->id
        );

        $technicianChanged = (int) $installation->technician_id !== (int) $technician->id;

        $installation->update([
            'technician_id' => $technician->id,
            'assigned_at' => $technicianChanged || ! $installation->assigned_at ? now() : $installation->assigned_at,
            'installation_date' => $validated['installation_date'],
            'installation_time' => $validated['installation_time'],
            'status' => Installation::STATUS_SCHEDULED,
            'customer_reschedule_reason' => trim($validated['customer_reschedule_reason']),
            'customer_rescheduled_at' => now(),
        ]);

        if ($installation->application) {
            $installation->application->update(['status' => \App\Models\Application::STATUS_SCHEDULED]);
        }

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Installation rescheduled by admin for '.$installation->installation_date?->format('F d, Y').' at '.Carbon::parse($installation->installation_time)->format('g:i A').'. Reason shown to customer: '.$installation->customer_reschedule_reason,
        ]);

        system_log(
            'Installation Rescheduled',
            'Rescheduled '.$installation->displayJobReference().' for '.$installation->installation_date?->format('M d, Y').' at '.Carbon::parse($installation->installation_time)->format('h:i A').'.'
        );

        AppNotificationSender::send(
            $technician,
            'Installation rescheduled',
            'Installation '.$installation->displayJobReference().' has a new schedule.',
            route('technician.installations.show', $installation),
            'installation_rescheduled',
            ['installation_id' => $installation->id, 'application_id' => $installation->application_id],
            auth()->id()
        );

        if ($installation->application?->user) {
            AppNotificationSender::send(
                $installation->application->user,
                'Installation rescheduled',
                $installation->customerRescheduleMessage(),
                route('customer.application.show'),
                'installation_rescheduled_customer',
                ['installation_id' => $installation->id, 'application_id' => $installation->application_id],
                auth()->id()
            );
        }

        return back()->with('success', 'Installation rescheduled successfully.');
    }
}
