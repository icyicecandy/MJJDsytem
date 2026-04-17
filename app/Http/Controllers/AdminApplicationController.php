<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Installation;
use App\Models\User;
use App\Support\AppNotificationSender;
use App\Support\CustomerEmailSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $query = Application::query()->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $applications = $query->paginate(10)->withQueryString();

        return view('admin.applications.index', compact('applications', 'status'));
    }

    public function show(Application $application)
    {
        $application->load(['installation', 'assessor']);
        $technicians = User::where('role', 'technician')->orderBy('name')->get();

        return view('admin.applications.show', compact('application', 'technicians'));
    }

    public function updateStatus(Request $request, Application $application)
    {
        $previousStatus = $application->status;

        $validated = $request->validate([
            'status' => ['required', 'in:submitted,under_review,approved,rejected'],
        ]);

        $updateData = ['status' => $validated['status']];

        if ($this->supportsAssessmentFields()) {
            $assessment = $request->validate([
                'serviceability_status' => ['required', 'in:pending,serviceable,not_serviceable'],
                'slot_availability_status' => ['required', 'in:pending,available,full,not_applicable'],
                'assessment_notes' => ['nullable', 'string'],
                'rejection_reason' => ['nullable', 'string'],
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

            if ($validated['status'] === 'rejected' && blank($assessment['rejection_reason'] ?? null)) {
                return back()
                    ->withErrors(['rejection_reason' => 'Please record the rejection reason before rejecting the application.'])
                    ->withInput();
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
                'rejection_reason' => ['required', 'string'],
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
                'under_review' => 'Your application is now under review.',
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
            'installation_time' => ['nullable'],
        ]);

        if (! in_array($application->status, ['approved', 'scheduled'], true)) {
            return back()->withErrors([
                'schedule' => 'Applications must be approved before they can be scheduled.',
            ]);
        }

        if (in_array($application->status, ['rejected', 'installed'], true)) {
            return back()->withErrors([
                'schedule' => $application->status === 'rejected'
                    ? 'Rejected applications cannot be scheduled.'
                    : 'Installation already completed. Cannot reschedule.',
            ]);
        }

        $technician = User::find($validated['technician_id']);
        if (! $technician || ! $technician->isTechnician()) {
            return back()
                ->withErrors(['technician_id' => 'Selected user is not a technician.'])
                ->withInput();
        }

        $installation = Installation::where('application_id', $application->id)->first();
        if ($installation && $installation->status === 'completed') {
            return back()->withErrors([
                'schedule' => 'This installation has already been completed.',
            ]);
        }

        $installation = Installation::updateOrCreate(
            ['application_id' => $application->id],
            [
                'technician_id' => $validated['technician_id'],
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
}
