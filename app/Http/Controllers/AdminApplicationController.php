<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Installation;
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
        $application->load(['installation', 'assessor']);
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

        if (in_array($application->status, ['approved', 'scheduled', 'installed', 'completed', 'rejected'], true)) {
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
        }

        if ($validated['status'] === 'rejected' && $previousStatus !== 'rejected') {
            CustomerEmailSender::sendApplicationRejected($application);
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

        if ($application->status !== 'approved') {
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
        if ($installation && in_array($installation->status, ['scheduled', 'completed'], true)) {
            return back()->withErrors([
                'schedule' => $installation->status === 'scheduled'
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
                'status' => 'scheduled',
            ]
        );

        $application->update([
            'status' => 'scheduled',
        ]);

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
