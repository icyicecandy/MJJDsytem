<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Models\TechnicianUnavailabilityRequest;
use App\Services\WorkflowTransaction;
use App\Support\AppNotificationSender;
use App\Support\CustomerEmailSender;
use App\Support\StaffWorkflowEmailSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicianUnavailabilityController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                TechnicianUnavailabilityRequest::STATUS_PENDING,
                TechnicianUnavailabilityRequest::STATUS_APPROVED,
                TechnicianUnavailabilityRequest::STATUS_CLARIFICATION,
                TechnicianUnavailabilityRequest::STATUS_REJECTED,
            ])],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $status = $validated['status'] ?? '';
        $search = trim((string) ($validated['search'] ?? ''));

        $requests = TechnicianUnavailabilityRequest::query()
            ->with(['technician', 'reviewer'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->whereHas('technician', fn ($technicianQuery) => $technicianQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('technician_id', 'like', "%{$search}%")))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'clarification_requested' THEN 1 ELSE 2 END")
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $requests->getCollection()->each(function (TechnicianUnavailabilityRequest $unavailability): void {
            $unavailability->setAttribute('affected_installations_count', $unavailability->affectedInstallations()->count());
        });

        $pendingUnavailabilityCount = TechnicianUnavailabilityRequest::query()
            ->whereIn('status', [
                TechnicianUnavailabilityRequest::STATUS_PENDING,
                TechnicianUnavailabilityRequest::STATUS_CLARIFICATION,
            ])
            ->count();

        return view('admin.unavailability.index', compact('requests', 'status', 'search', 'pendingUnavailabilityCount'));
    }

    public function show(TechnicianUnavailabilityRequest $unavailability)
    {
        $unavailability->load(['technician', 'reviewer']);

        return view('admin.unavailability.show', [
            'unavailability' => $unavailability,
            'affectedInstallations' => $unavailability->affectedInstallations(),
        ]);
    }

    public function decision(Request $request, TechnicianUnavailabilityRequest $unavailability): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'clarification', 'reject'])],
            'approved_start_at' => ['nullable', 'date', 'required_if:decision,approve'],
            'approved_end_at' => ['nullable', 'date', 'required_if:decision,approve', 'after:approved_start_at'],
            'admin_note' => ['nullable', 'string', 'max:1500', 'required_if:decision,clarification,reject'],
        ]);

        WorkflowTransaction::run(function () use ($request, $validated, $unavailability): void {
            $current = TechnicianUnavailabilityRequest::query()->lockForUpdate()->findOrFail($unavailability->id);
            abort_unless(in_array($current->status, [
                TechnicianUnavailabilityRequest::STATUS_PENDING,
                TechnicianUnavailabilityRequest::STATUS_CLARIFICATION,
            ], true), 422);

            $status = match ($validated['decision']) {
                'approve' => TechnicianUnavailabilityRequest::STATUS_APPROVED,
                'clarification' => TechnicianUnavailabilityRequest::STATUS_CLARIFICATION,
                'reject' => TechnicianUnavailabilityRequest::STATUS_REJECTED,
            };

            if ($validated['decision'] === 'approve') {
                $affectedInstallations = $current->affectedInstallations(
                    $validated['approved_start_at'],
                    $validated['approved_end_at']
                );

                if ($affectedInstallations->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'decision' => $affectedInstallations->count().' affected '.str('installation')->plural($affectedInstallations->count()).' must be reassigned or moved to the reschedule queue before approval.',
                    ]);
                }
            }

            if ($validated['decision'] === 'approve'
                && TechnicianUnavailabilityRequest::blocksPeriodFor(
                    $current->technician_id,
                    $validated['approved_start_at'],
                    $validated['approved_end_at'],
                    $current->id
                )) {
                throw ValidationException::withMessages([
                    'approved_start_at' => 'The approved period overlaps another active unavailability request for this technician.',
                ]);
            }

            $current->update([
                'status' => $status,
                'approved_start_at' => $validated['decision'] === 'approve' ? $validated['approved_start_at'] : null,
                'approved_end_at' => $validated['decision'] === 'approve' ? $validated['approved_end_at'] : null,
                'admin_note' => trim((string) ($validated['admin_note'] ?? '')) ?: null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $current->loadMissing('technician');
            AppNotificationSender::send(
                $current->technician,
                'Unavailability request '.$current->statusLabel(),
                $current->admin_note ?: 'Open the request to review the administrator decision.',
                route('technician.unavailability.index'),
                'technician_unavailability_decision',
                ['unavailability_request_id' => $current->id, 'status' => $current->status],
                $request->user()->id
            );

            system_log(
                'Technician Unavailability '.ucfirst(str_replace('_', ' ', $status)),
                $current->reference().' for '.$current->technician->name.' was marked '.$current->statusLabel().' by '.$request->user()->name.'.'
            );
        });

        $current = $unavailability->fresh(['technician']);
        StaffWorkflowEmailSender::sendUnavailabilityDecision($current);

        return redirect()->route('admin.unavailability.show', $current)
            ->with('success', 'The request was updated and the technician was notified in the system and by email.');
    }

    public function requireReschedule(Request $request, TechnicianUnavailabilityRequest $unavailability, Installation $installation): RedirectResponse
    {
        $validated = $request->validate([
            'coverage_note' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        WorkflowTransaction::installation($installation, function (Installation $current) use ($request, $validated, $unavailability): void {
            $unavailability->refresh();
            abort_unless(in_array($unavailability->status, TechnicianUnavailabilityRequest::BLOCKING_STATUSES, true), 422);
            abort_unless((int) $current->technician_id === (int) $unavailability->technician_id, 422);
            abort_unless(in_array($current->status, [Installation::STATUS_SCHEDULED, Installation::STATUS_ASSIGNED, Installation::STATUS_IN_PROGRESS], true), 422);
            abort_unless($unavailability->affectedInstallations()->contains('id', $current->id), 422);

            $current->update([
                'status' => Installation::STATUS_RESCHEDULE_REQUIRED,
                'technician_remarks' => trim($validated['coverage_note']),
            ]);

            InstallationLog::create([
                'installation_id' => $current->id,
                'user_id' => $request->user()->id,
                'notes' => 'Administrator moved the job to the reschedule queue because of '.$unavailability->reference().'. Operational note: '.trim($validated['coverage_note']),
            ]);

            AppNotificationSender::send(
                $unavailability->technician,
                'Installation coverage updated',
                $current->displayJobReference().' was moved to the reschedule queue by the administrator.',
                route('technician.installations.show', $current),
                'installation_coverage_updated',
                ['installation_id' => $current->id, 'unavailability_request_id' => $unavailability->id],
                $request->user()->id
            );

            if ($current->application?->user) {
                AppNotificationSender::send(
                    $current->application->user,
                    'Installation needs a new schedule',
                    'An operational availability change affected your appointment. The administrator will send another update after a replacement schedule is confirmed.',
                    route('customer.application.show'),
                    'installation_requires_new_schedule',
                    ['installation_id' => $current->id, 'application_id' => $current->application_id],
                    $request->user()->id
                );
            }

            CustomerEmailSender::sendInstallationRequiresNewSchedule($current->fresh(['application.user']));

            system_log('Installation Coverage Required', $current->displayJobReference().' was moved to the reschedule queue because of '.$unavailability->reference().'.');
        });

        return back()->with('success', 'The installation was moved to the reschedule queue. Open the application to assign a replacement schedule.');
    }

    public function evidence(TechnicianUnavailabilityRequest $unavailability): StreamedResponse
    {
        abort_unless($unavailability->evidence_path && Storage::disk('local')->exists($unavailability->evidence_path), 404);

        return Storage::disk('local')->download(
            $unavailability->evidence_path,
            $unavailability->evidence_original_name ?: basename($unavailability->evidence_path)
        );
    }
}
