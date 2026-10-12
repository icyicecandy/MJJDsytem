<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\TechnicianUnavailabilityRequest;
use App\Models\User;
use App\Services\WorkflowTransaction;
use App\Support\AppNotificationSender;
use App\Support\StaffWorkflowEmailSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnavailabilityController extends Controller
{
    public function index(Request $request)
    {
        $requests = $request->user()->unavailabilityRequests()
            ->with('reviewer')
            ->latest('requested_start_at')
            ->paginate(12);

        $requests->getCollection()->each(function (TechnicianUnavailabilityRequest $unavailability): void {
            $unavailability->setAttribute('affected_installations_count', $unavailability->affectedInstallations()->count());
        });

        return view('technician.unavailability.index', [
            'requests' => $requests,
            'categories' => TechnicianUnavailabilityRequest::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedRequest($request);

        $unavailability = WorkflowTransaction::run(function () use ($request, $validated) {
            $this->ensureNoOverlap($request->user(), $validated['requested_start_at'], $validated['requested_end_at']);

            $evidencePath = null;
            $evidenceName = null;
            if ($request->hasFile('evidence')) {
                $evidencePath = $request->file('evidence')->store('technician-unavailability', 'local');
                WorkflowTransaction::trackUpload($evidencePath, 'local');
                $evidenceName = $request->file('evidence')->getClientOriginalName();
            }

            $unavailability = TechnicianUnavailabilityRequest::create([
                'technician_id' => $request->user()->id,
                'requested_start_at' => $validated['requested_start_at'],
                'requested_end_at' => $validated['requested_end_at'],
                'category' => $validated['category'],
                'reason' => trim($validated['reason']),
                'evidence_path' => $evidencePath,
                'evidence_original_name' => $evidenceName,
                'status' => TechnicianUnavailabilityRequest::STATUS_PENDING,
            ]);

            $affectedCount = $unavailability->affectedInstallations()->count();
            $admins = User::query()->where('role', 'admin')->whereNull('disabled_at')->get();
            AppNotificationSender::sendToUsers(
                $admins,
                'Technician unavailability needs review',
                $request->user()->name.' reported unavailability. '.$affectedCount.' assigned '.str('installation')->plural($affectedCount).' may need coverage.',
                route('admin.unavailability.show', $unavailability),
                'technician_unavailability_submitted',
                ['unavailability_request_id' => $unavailability->id, 'affected_installations' => $affectedCount],
                $request->user()->id
            );

            system_log(
                'Technician Unavailability Submitted',
                $request->user()->name.' submitted '.$unavailability->reference().' for admin review. '.$affectedCount.' assigned installation(s) overlap the requested period.'
            );

            return $unavailability;
        });

        StaffWorkflowEmailSender::sendUnavailabilitySubmitted($unavailability);

        return redirect()->route('technician.unavailability.index')
            ->with('success', 'Unavailability request submitted. New assignments are temporarily blocked during the requested period while the administrator reviews it.');
    }

    public function resubmit(Request $request, TechnicianUnavailabilityRequest $unavailability): RedirectResponse
    {
        $this->authorizeTechnicianRequest($request, $unavailability);
        abort_unless($unavailability->status === TechnicianUnavailabilityRequest::STATUS_CLARIFICATION, 422);

        $validated = $this->validatedRequest($request);
        $oldEvidencePath = $unavailability->evidence_path;

        WorkflowTransaction::run(function () use ($request, $validated, $unavailability): void {
            $current = TechnicianUnavailabilityRequest::query()->lockForUpdate()->findOrFail($unavailability->id);
            abort_unless($current->status === TechnicianUnavailabilityRequest::STATUS_CLARIFICATION, 422);
            $this->ensureNoOverlap($request->user(), $validated['requested_start_at'], $validated['requested_end_at'], $current->id);

            $updates = [
                'requested_start_at' => $validated['requested_start_at'],
                'requested_end_at' => $validated['requested_end_at'],
                'category' => $validated['category'],
                'reason' => trim($validated['reason']),
                'status' => TechnicianUnavailabilityRequest::STATUS_PENDING,
                'approved_start_at' => null,
                'approved_end_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ];

            if ($request->hasFile('evidence')) {
                $updates['evidence_path'] = $request->file('evidence')->store('technician-unavailability', 'local');
                WorkflowTransaction::trackUpload($updates['evidence_path'], 'local');
                $updates['evidence_original_name'] = $request->file('evidence')->getClientOriginalName();
            }

            $current->update($updates);
            $affectedCount = $current->fresh()->affectedInstallations()->count();
            $admins = User::query()->where('role', 'admin')->whereNull('disabled_at')->get();
            AppNotificationSender::sendToUsers(
                $admins,
                'Technician unavailability resubmitted',
                $request->user()->name.' updated '.$current->reference().' after the clarification request.',
                route('admin.unavailability.show', $current),
                'technician_unavailability_resubmitted',
                ['unavailability_request_id' => $current->id, 'affected_installations' => $affectedCount],
                $request->user()->id
            );

            system_log('Technician Unavailability Resubmitted', $current->reference().' was updated and returned for admin review.');
        });

        if ($request->hasFile('evidence') && $oldEvidencePath) {
            Storage::disk('local')->delete($oldEvidencePath);
        }

        StaffWorkflowEmailSender::sendUnavailabilitySubmitted($unavailability->fresh('technician'));

        return back()->with('success', 'The request was updated and returned to the administrator for review.');
    }

    public function evidence(Request $request, TechnicianUnavailabilityRequest $unavailability): StreamedResponse
    {
        $this->authorizeTechnicianRequest($request, $unavailability);
        abort_unless($unavailability->evidence_path && Storage::disk('local')->exists($unavailability->evidence_path), 404);

        return Storage::disk('local')->download(
            $unavailability->evidence_path,
            $unavailability->evidence_original_name ?: basename($unavailability->evidence_path)
        );
    }

    private function validatedRequest(Request $request): array
    {
        return $request->validate([
            'requested_start_at' => ['required', 'date'],
            'requested_end_at' => ['required', 'date', 'after:requested_start_at'],
            'category' => ['required', Rule::in(array_keys(TechnicianUnavailabilityRequest::CATEGORIES))],
            'reason' => ['required', 'string', 'min:10', 'max:1500'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'requested_end_at.after' => 'The end date and time must be later than the start date and time.',
            'reason.min' => 'Briefly explain the reason using at least 10 characters.',
            'evidence.mimes' => 'Optional evidence must be a JPG, PNG, WebP, or PDF file.',
            'evidence.max' => 'Optional evidence must not exceed 5 MB.',
        ]);
    }

    private function ensureNoOverlap(User $technician, string $start, string $end, ?int $ignoreRequestId = null): void
    {
        if (TechnicianUnavailabilityRequest::blocksPeriodFor($technician->id, $start, $end, $ignoreRequestId)) {
            throw ValidationException::withMessages([
                'requested_start_at' => 'This period overlaps another pending, clarification, or approved unavailability request.',
            ]);
        }
    }

    private function authorizeTechnicianRequest(Request $request, TechnicianUnavailabilityRequest $unavailability): void
    {
        abort_unless((int) $unavailability->technician_id === (int) $request->user()->id, 403);
    }
}
