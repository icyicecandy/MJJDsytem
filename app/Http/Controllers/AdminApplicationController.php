<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Models\User;
use App\Support\AppNotificationSender;
use App\Support\CustomerEmailSender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $query = Application::query()
            ->with('user')
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('reference_no', 'like', '%' . $search . '%')
                    ->orWhere('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('mobile_number', 'like', '%' . $search . '%')
                    ->orWhere('plan_code', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $applications = $query->paginate(10)->withQueryString();

        return view('admin.applications.index', compact('applications', 'status', 'search'));
    }

    public function show(Application $application)
    {
        $application->load(['installation.technician', 'installation.logs.user', 'assessor']);
        $technicians = User::where('role', 'technician')->orderBy('name')->get();
        $activeSchedules = Installation::query()
            ->whereIn('status', Installation::activeSchedulingStatuses())
            ->whereNotNull('technician_id')
            ->whereNotNull('installation_date')
            ->whereNotNull('installation_time')
            ->when($application->installation?->id, fn ($query, $installationId) => $query->where('id', '!=', $installationId))
            ->get(['id', 'technician_id', 'installation_date', 'installation_time', 'status']);

        return view('admin.applications.show', compact('application', 'technicians', 'activeSchedules'));
    }

    public function updateStatus(Request $request, Application $application)
    {
        $previousStatus = $application->status;

        if (in_array($application->status, [
            Application::STATUS_APPROVED,
            Application::STATUS_SCHEDULED,
            Application::STATUS_CANCELLATION_REQUESTED,
            Application::STATUS_INSTALLED,
            Application::STATUS_ACTIVATED,
            'completed',
            Application::STATUS_REJECTED,
            Application::STATUS_CANCELLED,
        ], true)) {
            return back()->withErrors([
                'status' => 'This application decision is already locked.',
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,under_review,rejected'],
        ]);

        $updateData = ['status' => $validated['status']];

        if ($this->supportsAssessmentFields()) {
            $assessment = $request->validate([
                'serviceability_status' => ['required', 'in:pending,serviceable,not_serviceable'],
                'slot_availability_status' => ['required', 'in:pending,available,full,not_applicable'],
                'assessment_notes' => ['nullable', 'string'],
                'rejection_reason' => [
                    Rule::requiredIf($validated['status'] === 'rejected'),
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ], [
                'rejection_reason.required' => 'Please record the rejection reason before rejecting the application.',
            ]);

            if ($validated['status'] === 'approved') {
                if ($assessment['serviceability_status'] !== 'serviceable') {
                    return back()
                        ->withErrors(['serviceability_status' => 'Only serviceable applications can be approved.'])
                        ->withInput();
                }

                if ($assessment['slot_availability_status'] !== 'available') {
                    return back()
                        ->withErrors(['slot_availability_status' => 'Only applications with available slots can be approved.'])
                        ->withInput();
                }
            }

            $updateData = array_merge($updateData, [
                'serviceability_status' => $assessment['serviceability_status'],
                'slot_availability_status' => $assessment['slot_availability_status'],
                'assessment_notes' => $assessment['assessment_notes'] ?? null,
                'assessed_by' => auth()->id(),
                'assessed_at' => now(),
                'rejection_reason' => $validated['status'] === 'rejected'
                    ? ($assessment['rejection_reason'] ?? null)
                    : null,
            ]);
        } elseif ($validated['status'] === 'rejected' && Schema::hasColumn('applications', 'rejection_reason')) {
            $request->validate([
                'rejection_reason' => ['required', 'string', 'max:1000'],
            ], [
                'rejection_reason.required' => 'Please record the rejection reason before rejecting the application.',
            ]);
            $updateData['rejection_reason'] = $request->input('rejection_reason');
        } elseif ($validated['status'] === 'approved' && Schema::hasColumn('applications', 'rejection_reason')) {
            $updateData['rejection_reason'] = null;
        }

        $application->update($updateData);
        $application->refresh();

        if ($application->user) {
            $statusLabel = ucfirst(str_replace('_', ' ', $validated['status']));
            $message = match ($validated['status']) {
                'under_review' => 'Your application is under review while we confirm serviceability and schedule availability.',
                'approved' => 'Your application was approved and is ready for scheduling.',
                'rejected' => 'Your application was rejected. Please review the latest notes in your application page.',
                default => 'Your application status was updated.',
            };

            AppNotificationSender::send(
                $application->user,
                'Application status updated: ' . $statusLabel,
                $message,
                route('customer.application.show'),
                'application_status',
                ['application_id' => $application->id, 'status' => $validated['status']],
                auth()->id()
            );
        }

        if ($validated['status'] === 'approved' && $previousStatus !== 'approved') {
            CustomerEmailSender::sendApplicationApproved($application);

            system_log(
                'Application Approved',
                'Application ' . $application->reference_no . ' was approved by ' . auth()->user()->name . '.'
            );
        }

        if ($validated['status'] === 'rejected' && $previousStatus !== 'rejected') {
            CustomerEmailSender::sendApplicationRejected($application);

            system_log(
                'Application Rejected',
                'Application ' . $application->reference_no . ' was rejected by ' . auth()->user()->name . '.'
            );
        }

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', 'Application status updated.');
    }

    public function schedule(Request $request, Application $application)
    {
        $validated = $request->validate([
            'technician_id' => ['required', 'exists:users,id'],
            'installation_date' => ['required', 'date'],
            'installation_time' => ['required', Rule::in(Installation::AVAILABLE_TIME_SLOTS)],
        ]);

        if ($application->status !== Application::STATUS_APPROVED) {
            return back()->withErrors([
                'schedule' => 'Applications must be approved before they can be scheduled.',
            ]);
        }

        $technician = User::find($validated['technician_id']);
        if (! $technician || ! $technician->isTechnician()) {
            return back()
                ->withErrors(['technician_id' => 'Selected user is not a technician.'])
                ->withInput();
        }

        $installation = Installation::where('application_id', $application->id)->first();
        if ($installation && (in_array($installation->status, [Installation::STATUS_SCHEDULED], true) || in_array($installation->status, Installation::completedStatuses(), true))) {
            return back()->withErrors([
                'schedule' => $installation->status === Installation::STATUS_SCHEDULED
                    ? 'This application has already been scheduled.'
                    : 'This installation has already been completed.',
            ]);
        }

        if ($this->technicianHasScheduleConflict(
            technicianId: (int) $validated['technician_id'],
            installationDate: $validated['installation_date'],
            installationTime: $validated['installation_time'],
            ignoreInstallationId: $installation?->id,
        )) {
            return back()
                ->withErrors(['installation_time' => 'This technician already has an installation scheduled during that time slot.'])
                ->withInput();
        }

        $installation = Installation::updateOrCreate(
            ['application_id' => $application->id],
            [
                'technician_id' => $validated['technician_id'],
                'assigned_at' => now(),
                'installation_date' => $validated['installation_date'],
                'installation_time' => $validated['installation_time'],
                'status' => Installation::STATUS_SCHEDULED,
            ]
        );

        $application->update([
            'status' => Application::STATUS_SCHEDULED,
        ]);

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Installation scheduled for ' . $installation->installation_date?->format('F d, Y') . ' at ' . Carbon::parse($installation->installation_time)->format('g:i A') . '.',
        ]);

        system_log(
            'Assigned Technician',
            'Assigned technician ' . $technician->name . ' to Application ' . $application->reference_no . '.'
        );

        system_log(
            'Installation Scheduled',
            'Scheduled installation for Application ' . $application->reference_no . ' on ' . $installation->installation_date?->format('M d, Y') . ' at ' . Carbon::parse($installation->installation_time)->format('h:i A') . '.'
        );

        AppNotificationSender::send(
            $technician,
            'New installation assigned',
            'You were assigned an installation scheduled for ' . $installation->installation_date?->format('F d, Y') . '.',
            route('technician.installations.show', $installation),
            'installation_assigned',
            ['installation_id' => $installation->id, 'application_id' => $application->id],
            auth()->id()
        );

        if ($application->user) {
            AppNotificationSender::send(
                $application->user,
                'Installation scheduled',
                'Your installation has been scheduled. Open your application page to view the details.',
                route('customer.application.show'),
                'installation_scheduled',
                ['installation_id' => $installation->id, 'application_id' => $application->id],
                auth()->id()
            );
        }

        $application->loadMissing('installation', 'user');
        CustomerEmailSender::sendApplicationScheduled($application);

        return back()->with('success', 'Installation scheduled successfully.');
    }

    public function cancellationDecision(Request $request, Application $application)
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'cancellation_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($application->status !== Application::STATUS_CANCELLATION_REQUESTED) {
            return back()->withErrors([
                'cancellation' => 'This application is not waiting for cancellation review.',
            ]);
        }

        $application->loadMissing(['installation', 'user']);
        $installation = $application->installation;
        $notesSuffix = filled($validated['cancellation_notes'] ?? null)
            ? ' Notes: ' . $validated['cancellation_notes']
            : '';

        if ($validated['decision'] === 'approve') {
            $application->update(['status' => Application::STATUS_CANCELLED]);

            if ($installation) {
                $installation->update(['status' => Installation::STATUS_CANCELLED]);

                InstallationLog::create([
                    'installation_id' => $installation->id,
                    'user_id' => auth()->id(),
                    'notes' => 'Cancellation request approved by admin.' . $notesSuffix,
                ]);
            }

            if ($application->user) {
                AppNotificationSender::send(
                    $application->user,
                    'Cancellation approved',
                    'Your application cancellation request was approved.',
                    route('customer.application.show'),
                    'application_cancelled',
                    ['application_id' => $application->id, 'installation_id' => $installation?->id],
                    auth()->id()
                );
            }

            system_log(
                'Application Cancelled',
                'Application ' . $application->reference_no . ' cancellation request was approved by ' . auth()->user()->name . '.'
            );

            return back()->with('success', 'Cancellation request approved.');
        }

        $application->update([
            'status' => $installation ? Application::STATUS_SCHEDULED : Application::STATUS_APPROVED,
        ]);

        if ($installation) {
            InstallationLog::create([
                'installation_id' => $installation->id,
                'user_id' => auth()->id(),
                'notes' => 'Cancellation request rejected by admin. Installation remains scheduled.' . $notesSuffix,
            ]);
        }

        if ($application->user) {
            AppNotificationSender::send(
                $application->user,
                'Cancellation request rejected',
                'Your installation schedule remains active.',
                route('customer.application.show'),
                'application_cancellation_rejected',
                ['application_id' => $application->id, 'installation_id' => $installation?->id],
                auth()->id()
            );
        }

        return back()->with('success', 'Cancellation request rejected. Installation remains scheduled.');
    }

    public function rescheduleDecision(Request $request, Application $application)
    {
        $isApprove = $request->input('decision') === 'approve';
        $isReject = $request->input('decision') === 'reject';

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'technician_id' => [Rule::requiredIf($isApprove), 'nullable', 'exists:users,id'],
            'installation_date' => [Rule::requiredIf($isApprove), 'nullable', 'date'],
            'installation_time' => [Rule::requiredIf($isApprove), 'nullable', Rule::in(Installation::AVAILABLE_TIME_SLOTS)],
            'reschedule_rejection_reason' => [
                Rule::requiredIf($isReject),
                'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'reschedule_rejection_reason.required' => 'Please provide a reason before rejecting the reschedule request.',
        ]);

        $application->loadMissing(['installation.technician', 'user']);
        $installation = $application->installation;

        if (! $installation || ! $installation->hasPendingCustomerRescheduleRequest()) {
            return back()->withErrors([
                'reschedule' => 'This application is not waiting for customer reschedule review.',
            ]);
        }

        if ($validated['decision'] === 'reject') {
            $reason = trim((string) $validated['reschedule_rejection_reason']);

            if ($reason === '') {
                return back()
                    ->withErrors(['reschedule_rejection_reason' => 'Please provide a reason before rejecting the reschedule request.'])
                    ->withInput();
            }

            $installation->update([
                'reschedule_reviewed_at' => now(),
                'reschedule_rejection_reason' => $reason,
            ]);

            InstallationLog::create([
                'installation_id' => $installation->id,
                'user_id' => auth()->id(),
                'notes' => 'Customer reschedule request rejected by admin. Reason: ' . $reason,
            ]);

            if ($application->user) {
                AppNotificationSender::send(
                    $application->user,
                    'Reschedule request rejected',
                    'Your installation schedule remains active. Reason: ' . $reason,
                    route('customer.application.show'),
                    'installation_reschedule_rejected',
                    ['application_id' => $application->id, 'installation_id' => $installation->id],
                    auth()->id()
                );
            }

            system_log(
                'Installation Reschedule Rejected',
                'Rejected customer reschedule request for Application ' . $application->reference_no . '.'
            );

            return back()->with('success', 'Reschedule request rejected. The original schedule remains active.');
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
            'reschedule_reviewed_at' => now(),
            'reschedule_rejection_reason' => null,
        ]);

        $application->update(['status' => Application::STATUS_SCHEDULED]);

        $scheduleLabel = $installation->fresh()->installation_date?->format('F d, Y')
            . ' at '
            . Carbon::parse($validated['installation_time'])->format('g:i A');

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Customer reschedule request approved by admin. New schedule: ' . $scheduleLabel . '.',
        ]);

        system_log(
            'Installation Reschedule Approved',
            'Approved customer reschedule request for Application ' . $application->reference_no . ' to ' . $scheduleLabel . '.'
        );

        AppNotificationSender::send(
            $technician,
            'Installation rescheduled',
            'Installation ' . $installation->displayJobReference() . ' has a new schedule.',
            route('technician.installations.show', $installation),
            'installation_rescheduled',
            ['installation_id' => $installation->id, 'application_id' => $application->id],
            auth()->id()
        );

        if ($application->user) {
            AppNotificationSender::send(
                $application->user,
                'Reschedule request approved',
                'Your installation has been rescheduled to ' . $scheduleLabel . '.',
                route('customer.application.show'),
                'installation_reschedule_approved',
                ['application_id' => $application->id, 'installation_id' => $installation->id],
                auth()->id()
            );
        }

        return back()->with('success', 'Reschedule request approved and installation schedule updated.');
    }

    private function supportsAssessmentFields(): bool
    {
        return Schema::hasColumn('applications', 'serviceability_status')
            && Schema::hasColumn('applications', 'slot_availability_status')
            && Schema::hasColumn('applications', 'assessment_notes')
            && Schema::hasColumn('applications', 'assessed_by')
            && Schema::hasColumn('applications', 'assessed_at');
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
