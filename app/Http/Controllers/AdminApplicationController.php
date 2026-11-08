<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationIdVerificationEvent;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\TechnicianSchedule;
use App\Services\WorkflowTransaction;
use App\Support\ApplicationIdentity;
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
        $validatedFilters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'requires_action' => ['nullable', 'boolean'],
        ]);
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $dateFrom = $validatedFilters['date_from'] ?? null;
        $dateTo = $validatedFilters['date_to'] ?? null;
        $technicianId = isset($validatedFilters['technician_id']) ? (int) $validatedFilters['technician_id'] : null;
        $requiresAction = $request->boolean('requires_action');

        $availableStatuses = [
            '',
            Application::STATUS_SUBMITTED,
            Application::STATUS_UNDER_REVIEW,
            Application::STATUS_APPROVED,
            Application::STATUS_CANCELLATION_REQUESTED,
            Application::STATUS_REJECTED,
            Application::STATUS_SCHEDULED,
            Application::STATUS_INSTALLED,
            Application::STATUS_ACTIVATED,
            Application::STATUS_CANCELLED,
            'reschedule',
        ];

        if (! in_array($status, $availableStatuses, true)) {
            $status = '';
        }

        $query = Application::query()
            ->with(['user', 'installation'])
            ->orderByDesc('created_at');

        if ($status === 'reschedule') {
            $this->applyRescheduleFilter($query);
        } elseif ($status !== '') {
            $query->where('status', $status);
        }

        $this->applyApplicationSearch($query, $search);
        $query
            ->when($dateFrom, fn ($builder) => $builder->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($builder) => $builder->whereDate('created_at', '<=', $dateTo))
            ->when($technicianId, fn ($builder) => $builder->whereHas('installation', fn ($installation) => $installation->where('technician_id', $technicianId)))
            ->when($requiresAction, fn ($builder) => $builder->where(function ($attention) {
                $attention->whereIn('status', [Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW, Application::STATUS_APPROVED, Application::STATUS_CANCELLATION_REQUESTED])
                    ->orWhereHas('installation', fn ($installation) => $installation
                        ->where('status', Installation::STATUS_RESCHEDULE_REQUIRED)
                        ->orWhere(fn ($request) => $request->whereNotNull('reschedule_requested_at')->whereNull('reschedule_reviewed_at')));
            }));

        $applications = $query->paginate(10)->withQueryString();

        $summaryQuery = Application::query();
        $this->applyApplicationSearch($summaryQuery, $search);

        $applicationSummary = [
            Application::STATUS_SUBMITTED => (clone $summaryQuery)->where('status', Application::STATUS_SUBMITTED)->count(),
            Application::STATUS_UNDER_REVIEW => (clone $summaryQuery)->where('status', Application::STATUS_UNDER_REVIEW)->count(),
            Application::STATUS_APPROVED => (clone $summaryQuery)->where('status', Application::STATUS_APPROVED)->count(),
            Application::STATUS_SCHEDULED => (clone $summaryQuery)->where('status', Application::STATUS_SCHEDULED)->count(),
            'reschedule' => $this->applyRescheduleFilter(clone $summaryQuery)->count(),
            Application::STATUS_CANCELLATION_REQUESTED => (clone $summaryQuery)->where('status', Application::STATUS_CANCELLATION_REQUESTED)->count(),
        ];

        $filterTechnicians = User::query()->technicians()->orderBy('name')->get(['id', 'name']);

        return view('admin.applications.index', compact('applications', 'applicationSummary', 'status', 'search', 'dateFrom', 'dateTo', 'technicianId', 'requiresAction', 'filterTechnicians'));
    }

    private function applyApplicationSearch($query, string $search)
    {
        if ($search === '') {
            return $query;
        }

        return $query->where(function ($builder) use ($search) {
            $builder
                ->where('reference_no', 'like', '%'.$search.'%')
                ->orWhere('first_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('mobile_number', 'like', '%'.$search.'%')
                ->orWhere('plan_code', 'like', '%'.$search.'%')
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', '%'.$search.'%');
                });
        });
    }

    private function applyRescheduleFilter($query)
    {
        return $query->whereHas('installation', function ($installationQuery) {
            $installationQuery
                ->where('status', Installation::STATUS_RESCHEDULE_REQUIRED)
                ->orWhere(function ($requestQuery) {
                    $requestQuery
                        ->whereNotNull('reschedule_requested_at')
                        ->whereNull('reschedule_reviewed_at');
                });
        });
    }

    public function show(Application $application)
    {
        $application->load(['installation.technician', 'installation.logs.user', 'assessor', 'idVerifier', 'idVerificationEvents.actor']);
        $technicians = User::query()
            ->where('role', 'technician')
            ->whereNull('disabled_at')
            ->where(function ($query) {
                $query->whereNull('technician_status')
                    ->orWhere('technician_status', '!=', 'on_leave');
            })
            ->orderBy('name')
            ->get();
        $scheduleAvailability = TechnicianSchedule::installationAvailabilityGrid(
            $technicians,
            $application,
            now()->startOfDay(),
            now()->addDays(89)->startOfDay(),
            $application->installation?->id,
            includeRouteDetails: true
        );

        $identityDuplicates = collect();
        if ($application->id_image_hash) {
            $identityDuplicates = Application::query()
                ->with('user')
                ->whereKeyNot($application->id)
                ->where('user_id', '!=', $application->user_id)
                ->where('id_image_hash', $application->id_image_hash)
                ->latest()
                ->get();
        }

        $applicationTimeline = collect([[
            'occurred_at' => $application->created_at,
            'actor' => $application->user?->name ?? 'Customer',
            'title' => 'Application submitted',
            'description' => 'Application '.$application->reference_no.' was submitted.',
        ]])
            ->merge($application->idVerificationEvents->map(fn ($event) => [
                'occurred_at' => $event->created_at,
                'actor' => $event->actor?->name ?? 'System',
                'title' => 'Identity decision: '.ucfirst($event->to_status),
                'description' => $event->reason ?: 'Identity documents were reviewed.',
            ]))
            ->merge(collect($application->installation?->logs ?? [])->map(fn ($log) => [
                'occurred_at' => $log->created_at,
                'actor' => $log->user?->name ?? 'System',
                'title' => 'Installation activity',
                'description' => $log->notes,
            ]))
            ->merge(SystemLog::query()
                ->with('user')
                ->where('description', 'like', '%'.$application->reference_no.'%')
                ->get()
                ->map(fn ($log) => [
                    'occurred_at' => $log->created_at,
                    'actor' => $log->user?->name ?? 'System',
                    'title' => $log->action,
                    'description' => $log->description,
                ]))
            ->sortByDesc('occurred_at')
            ->values();

        return view('admin.applications.show', compact(
            'application',
            'technicians',
            'scheduleAvailability',
            'identityDuplicates',
            'applicationTimeline'
        ));
    }

    public function updateStatus(Request $request, Application $application)
    {
        return WorkflowTransaction::application($application, function (Application $application) use ($request) {
            return $this->updateStatusWithinTransaction($request, $application);
        });
    }

    private function updateStatusWithinTransaction(Request $request, Application $application)
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

        if ($validated['status'] === Application::STATUS_APPROVED
            && $application->id_verification_status !== ApplicationIdentity::STATUS_VERIFIED) {
            $message = match ($application->id_verification_status) {
                ApplicationIdentity::STATUS_REJECTED => 'This identity submission was rejected. Wait for replacement documents and verify them before approving the application.',
                ApplicationIdentity::STATUS_PENDING => 'Verify the submitted identity documents before approving this application.',
                default => 'Identity verification is required before approving this application. Legacy records are not exempt from this requirement.',
            };

            return back()->withErrors([
                'status' => $message,
            ])->withInput();
        }

        $updateData = ['status' => $validated['status']];

        if ($this->supportsAssessmentFields()) {
            $assessment = $request->validate([
                'serviceability_status' => ['required', 'in:pending,serviceable,not_serviceable'],
                'slot_availability_status' => ['required', 'in:pending,available,full,not_applicable'],
                'assessment_notes' => ['nullable', 'string', 'max:2000'],
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
                'Application status updated: '.$statusLabel,
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
                'Application '.$application->reference_no.' was approved by '.auth()->user()->name.'.'
            );
        }

        if ($validated['status'] === 'rejected' && $previousStatus !== 'rejected') {
            CustomerEmailSender::sendApplicationRejected($application);

            system_log(
                'Application Rejected',
                'Application '.$application->reference_no.' was rejected by '.auth()->user()->name.'.'
            );
        }

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', 'Application status updated.');
    }

    public function schedule(Request $request, Application $application)
    {
        return WorkflowTransaction::application($application, function (Application $application) use ($request) {
            return $this->scheduleWithinTransaction($request, $application);
        }, $request->integer('technician_id') ?: null);
    }

    private function scheduleWithinTransaction(Request $request, Application $application)
    {
        $validated = $request->validate([
            'technician_id' => ['required', 'integer', 'exists:users,id'],
            'installation_date' => ['required', 'date_format:Y-m-d'],
            'installation_time' => ['required', Rule::in(Installation::AVAILABLE_TIME_SLOTS)],
        ]);

        if ($application->status !== Application::STATUS_APPROVED) {
            return back()->withErrors([
                'schedule' => 'Applications must be approved before they can be scheduled.',
            ]);
        }

        if ($application->id_verification_status !== null
            && $application->id_verification_status !== ApplicationIdentity::STATUS_VERIFIED) {
            return back()->withErrors([
                'schedule' => 'Technician scheduling is locked until the identity documents are verified.',
            ]);
        }

        $technician = TechnicianSchedule::eligibleTechnician((int) $validated['technician_id']);

        $installation = Installation::where('application_id', $application->id)->first();
        if ($installation && (in_array($installation->status, [Installation::STATUS_SCHEDULED], true) || in_array($installation->status, Installation::completedStatuses(), true))) {
            return back()->withErrors([
                'schedule' => $installation->status === Installation::STATUS_SCHEDULED
                    ? 'This application has already been scheduled.'
                    : 'This installation has already been completed.',
            ]);
        }

        TechnicianSchedule::assertInstallationAvailable(
            $technician,
            $application,
            $validated['installation_date'],
            $validated['installation_time'],
            $installation?->id
        );

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
            'notes' => 'Installation scheduled for '.$installation->installation_date?->format('F d, Y').' at '.Carbon::parse($installation->installation_time)->format('g:i A').'.',
        ]);

        system_log(
            'Assigned Technician',
            'Assigned technician '.$technician->name.' to Application '.$application->reference_no.'.'
        );

        system_log(
            'Installation Scheduled',
            'Scheduled installation for Application '.$application->reference_no.' on '.$installation->installation_date?->format('M d, Y').' at '.Carbon::parse($installation->installation_time)->format('h:i A').'.'
        );

        AppNotificationSender::send(
            $technician,
            'New installation assigned',
            'You were assigned an installation scheduled for '.$installation->installation_date?->format('F d, Y').'.',
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
        return WorkflowTransaction::application($application, function (Application $application) use ($request) {
            return $this->cancellationDecisionWithinTransaction($request, $application);
        });
    }

    private function cancellationDecisionWithinTransaction(Request $request, Application $application)
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
            ? ' Notes: '.$validated['cancellation_notes']
            : '';

        if ($validated['decision'] === 'approve') {
            $application->update([
                'status' => Application::STATUS_CANCELLED,
            ]);

            if ($installation) {
                $installation->update([
                    'status' => Installation::STATUS_CANCELLED,
                    'stop_work_reviewed_at' => now(),
                    'stop_work_reviewed_by' => auth()->id(),
                    'stopped_at' => now(),
                    'stop_work_admin_notes' => $validated['cancellation_notes'] ?? null,
                ]);

                InstallationLog::create([
                    'installation_id' => $installation->id,
                    'user_id' => auth()->id(),
                    'notes' => 'Cancellation request approved by admin.'.$notesSuffix,
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

            if ($installation?->technician) {
                AppNotificationSender::send(
                    $installation->technician,
                    'Stop Work request approved',
                    $application->reference_no.' was cancelled by the administrator. Work is closed.',
                    route('technician.installations.show', $installation),
                    'installation_stop_work_approved',
                    ['application_id' => $application->id, 'installation_id' => $installation->id],
                    auth()->id()
                );
            }

            system_log(
                'Application Cancelled',
                'Application '.$application->reference_no.' cancellation request was approved by '.auth()->user()->name.'.'
            );

            return back()->with('success', 'Cancellation request approved.');
        }

        $restoredApplicationStatus = $application->cancellation_previous_status
            ?: ($installation ? Application::STATUS_SCHEDULED : Application::STATUS_APPROVED);
        $application->update([
            'status' => $restoredApplicationStatus,
        ]);

        if ($installation) {
            $restoredInstallationStatus = $installation->cancellation_previous_status
                ?: Installation::STATUS_SCHEDULED;
            $installation->update([
                'status' => $restoredInstallationStatus,
                'stop_work_reviewed_at' => now(),
                'stop_work_reviewed_by' => auth()->id(),
                'stop_work_admin_notes' => $validated['cancellation_notes'] ?? null,
            ]);

            InstallationLog::create([
                'installation_id' => $installation->id,
                'user_id' => auth()->id(),
                'notes' => 'Stop Work / Cancellation Request rejected by admin. Restored application status to '
                    .$restoredApplicationStatus.' and installation status to '.$restoredInstallationStatus.'.'.$notesSuffix,
            ]);
        }

        if ($installation?->technician) {
            AppNotificationSender::send(
                $installation->technician,
                'Stop Work request rejected',
                $application->reference_no.' was restored to '.str_replace('_', ' ', $restoredInstallationStatus).'.',
                route('technician.installations.show', $installation),
                'installation_stop_work_rejected',
                ['application_id' => $application->id, 'installation_id' => $installation->id],
                auth()->id()
            );
        }

        if ($application->user) {
            AppNotificationSender::send(
                $application->user,
                'Cancellation request rejected',
                'Your Stop Work / Cancellation Request was rejected and the previous work status was restored.',
                route('customer.application.show'),
                'application_cancellation_rejected',
                ['application_id' => $application->id, 'installation_id' => $installation?->id],
                auth()->id()
            );
        }

        return back()->with('success', 'Request rejected. The exact previous application and installation states were restored.');
    }

    public function rescheduleDecision(Request $request, Application $application)
    {
        return WorkflowTransaction::application($application, function (Application $application) use ($request) {
            return $this->rescheduleDecisionWithinTransaction($request, $application);
        }, $request->integer('technician_id') ?: null);
    }

    private function rescheduleDecisionWithinTransaction(Request $request, Application $application)
    {
        $isApprove = $request->input('decision') === 'approve';
        $isReject = $request->input('decision') === 'reject';

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'technician_id' => [Rule::requiredIf($isApprove), 'nullable', 'integer', 'exists:users,id'],
            'installation_date' => [Rule::requiredIf($isApprove), 'nullable', 'date_format:Y-m-d'],
            'installation_time' => [Rule::requiredIf($isApprove), 'nullable', Rule::in(Installation::AVAILABLE_TIME_SLOTS)],
            'customer_reschedule_reason' => [Rule::requiredIf($isApprove), 'nullable', 'string', 'max:1000'],
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

        if (! $installation || ! $installation->hasPendingCustomerRescheduleRequest()
            || $application->status !== Application::STATUS_SCHEDULED
            || $installation->status !== Installation::STATUS_SCHEDULED) {
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
                'notes' => 'Customer reschedule request rejected by admin. Reason: '.$reason,
            ]);

            if ($application->user) {
                AppNotificationSender::send(
                    $application->user,
                    'Reschedule request rejected',
                    'Your installation schedule remains active. Reason: '.$reason,
                    route('customer.application.show'),
                    'installation_reschedule_rejected',
                    ['application_id' => $application->id, 'installation_id' => $installation->id],
                    auth()->id()
                );
            }

            CustomerEmailSender::sendInstallationRescheduled(
                $installation->fresh(['application.user', 'technician']),
                'Reschedule request not approved',
                'Your requested schedule change was not approved. The existing installation schedule remains active.',
                $reason
            );

            system_log(
                'Installation Reschedule Rejected',
                'Rejected customer reschedule request for Application '.$application->reference_no.'.'
            );

            return back()->with('success', 'Reschedule request rejected. The original schedule remains active.');
        }

        $technician = TechnicianSchedule::eligibleTechnician((int) $validated['technician_id']);

        TechnicianSchedule::assertInstallationAvailable(
            $technician,
            $application,
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
            'reschedule_reviewed_at' => now(),
            'reschedule_rejection_reason' => null,
            'customer_reschedule_reason' => trim($validated['customer_reschedule_reason']),
            'customer_rescheduled_at' => now(),
        ]);

        $application->update(['status' => Application::STATUS_SCHEDULED]);

        $scheduleLabel = $installation->fresh()->installation_date?->format('F d, Y')
            .' at '
            .Carbon::parse($validated['installation_time'])->format('g:i A');

        InstallationLog::create([
            'installation_id' => $installation->id,
            'user_id' => auth()->id(),
            'notes' => 'Customer reschedule request approved by admin. New schedule: '.$scheduleLabel.'. Reason shown to customer: '.$installation->customer_reschedule_reason,
        ]);

        system_log(
            'Installation Reschedule Approved',
            'Approved customer reschedule request for Application '.$application->reference_no.' to '.$scheduleLabel.'.'
        );

        AppNotificationSender::send(
            $technician,
            'Installation rescheduled',
            'Installation '.$installation->displayJobReference().' has a new schedule.',
            route('technician.installations.show', $installation),
            'installation_rescheduled',
            ['installation_id' => $installation->id, 'application_id' => $application->id],
            auth()->id()
        );

        if ($application->user) {
            AppNotificationSender::send(
                $application->user,
                'Reschedule request approved',
                $installation->customerRescheduleMessage(),
                route('customer.application.show'),
                'installation_reschedule_approved',
                ['application_id' => $application->id, 'installation_id' => $installation->id],
                auth()->id()
            );
        }

        CustomerEmailSender::sendInstallationRescheduled($installation->fresh(['application.user', 'technician']));

        return back()->with('success', 'Reschedule request approved and installation schedule updated.');
    }

    public function updateIdentityVerification(Request $request, Application $application)
    {
        return WorkflowTransaction::application($application, function (Application $application) use ($request) {
            $requestedStatus = (string) $request->input('id_verification_status');
            $isVerification = $requestedStatus === ApplicationIdentity::STATUS_VERIFIED;
            $applicationIsInReview = in_array($application->status, [
                Application::STATUS_SUBMITTED,
                Application::STATUS_UNDER_REVIEW,
            ], true);
            $applicationHasProgressed = in_array($application->status, [
                Application::STATUS_APPROVED,
                Application::STATUS_SCHEDULED,
                Application::STATUS_CANCELLATION_REQUESTED,
                Application::STATUS_INSTALLED,
                Application::STATUS_ACTIVATED,
                'completed',
            ], true);
            $isApprovedRecordCorrection = $applicationHasProgressed
                && $application->id_verification_status !== ApplicationIdentity::STATUS_VERIFIED
                && $isVerification;

            if (! $applicationIsInReview && ! $isApprovedRecordCorrection) {
                return back()->withErrors([
                    'id_verification_status' => 'Identity verification is locked after application approval. An approved identity cannot be changed to rejected.',
                ]);
            }

            $possibleDuplicate = $application->duplicate_id_image_detected;
            $reasonRequired = $requestedStatus === ApplicationIdentity::STATUS_REJECTED
                || ($isVerification && $possibleDuplicate);

            $validated = $request->validate([
                'id_verification_status' => ['required', Rule::in([
                    ApplicationIdentity::STATUS_VERIFIED,
                    ApplicationIdentity::STATUS_REJECTED,
                ])],
                'review_document_readable' => [Rule::excludeIf(! $isVerification), 'required', 'accepted'],
                'review_name_matches' => [Rule::excludeIf(! $isVerification), 'required', 'accepted'],
                'review_photo_matches' => [Rule::excludeIf(! $isVerification), 'required', 'accepted'],
                'id_verification_reason' => [Rule::requiredIf($reasonRequired), 'nullable', 'string', 'max:1000'],
            ], [
                'review_document_readable.required' => 'Confirm that the submitted ID is readable before verifying.',
                'review_document_readable.accepted' => 'Confirm that the submitted ID is readable before verifying.',
                'review_name_matches.required' => 'Confirm that the applicant name is consistent with the ID before verifying.',
                'review_name_matches.accepted' => 'Confirm that the applicant name is consistent with the ID before verifying.',
                'review_photo_matches.required' => 'Confirm that the ID portrait reasonably matches the holding-ID photo before verifying.',
                'review_photo_matches.accepted' => 'Confirm that the ID portrait reasonably matches the holding-ID photo before verifying.',
                'id_verification_reason.required' => $requestedStatus === ApplicationIdentity::STATUS_REJECTED
                    ? 'Record why the submitted identity document was rejected.'
                    : 'Explain why this possible duplicate is safe to verify.',
            ]);

            if (! $application->idImageUrl() && ! $application->idSelfieImageUrl()) {
                return back()->withErrors(['id_verification_status' => 'No identity document is available to verify.']);
            }

            $previousStatus = $application->id_verification_status;
            $reason = filled($validated['id_verification_reason'] ?? null)
                ? trim($validated['id_verification_reason'])
                : null;
            // Discard unconfirmed OCR-era suggestions. Confirmed legacy values retain
            // their fingerprint for compatibility but are no longer shown or required.
            $identityUpdates = $application->valid_id_number_fingerprint ? [] : [
                'valid_id_number' => null,
                'duplicate_id_number_detected' => false,
            ];

            $application->update($identityUpdates + [
                'id_verification_status' => $validated['id_verification_status'],
                'id_verification_reason' => $reason,
                'id_verified_by' => auth()->id(),
                'id_verified_at' => now(),
            ]);

            ApplicationIdVerificationEvent::create([
                'application_id' => $application->id,
                'actor_id' => auth()->id(),
                'from_status' => $previousStatus,
                'to_status' => $validated['id_verification_status'],
                'reason' => $reason ?: ($isVerification
                    ? 'ID type, identity photos, and applicant details confirmed during administrator review.'
                    : null),
            ]);

            $label = ucfirst($validated['id_verification_status']);
            if ($application->user) {
                AppNotificationSender::send(
                    $application->user,
                    'Identity document '.$validated['id_verification_status'],
                    $validated['id_verification_status'] === ApplicationIdentity::STATUS_VERIFIED
                        ? 'The identity documents for '.$application->reference_no.' were verified.'
                        : 'The identity documents for '.$application->reference_no.' need attention. Review the recorded reason.',
                    route('customer.application.show'),
                    'application_identity_'.$validated['id_verification_status'],
                    ['application_id' => $application->id, 'id_verification_status' => $validated['id_verification_status']],
                    auth()->id()
                );
            }

            if ($validated['id_verification_status'] === ApplicationIdentity::STATUS_REJECTED
                && $previousStatus !== ApplicationIdentity::STATUS_REJECTED) {
                CustomerEmailSender::sendIdentityResubmission($application->fresh(['user']));
            }

            system_log(
                'Application Identity '.$label,
                'Identity documents for '.$application->reference_no.' were marked '.$validated['id_verification_status'].' by '.auth()->user()->name.'.'
            );

            $successMessage = $isApprovedRecordCorrection
                ? 'Identity document verified. The inconsistent progressed record was corrected.'
                : 'Identity document marked as '.$label.'.';

            return redirect()
                ->to(route('admin.applications.show', $application).'#documents')
                ->with('success', $successMessage);
        });
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
