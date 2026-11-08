<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use App\Services\WorkflowTransaction;
use App\Support\AppNotificationSender;
use App\Support\CustomerEmailSender;
use App\Support\PublicUpload;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminTicketController extends Controller
{
    public function index(Request $request)
    {
        $validatedFilters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'requires_action' => ['nullable', 'boolean'],
        ]);
        $selectedStatus = $request->query('status', 'all');
        $selectedUrgency = strtolower(trim((string) $request->query('urgency', 'all')));
        $selectedTechnician = trim((string) $request->query('technician', 'all'));
        $search = trim((string) $request->query('search', ''));
        $dateFrom = $validatedFilters['date_from'] ?? null;
        $dateTo = $validatedFilters['date_to'] ?? null;
        $requiresAction = $request->boolean('requires_action');

        if (! in_array($selectedStatus, ['all', 'unassigned', 'open', 'queued', 'in_progress', 'resolved', 'closed', 'cancelled'], true)) {
            $selectedStatus = 'all';
        }
        if (! in_array($selectedUrgency, array_merge(['all'], Ticket::URGENCY_LEVELS), true)) {
            $selectedUrgency = 'all';
        }
        if ($selectedTechnician !== 'all' && $selectedTechnician !== 'unassigned' && ! ctype_digit($selectedTechnician)) {
            $selectedTechnician = 'all';
        }

        $ticketsQuery = Ticket::with(['user', 'assignedStaff'])
            ->queueOrdered();

        if ($search !== '') {
            $ticketsQuery->where(function ($query) use ($search) {
                $query->where('tracking_number', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($selectedUrgency !== 'all') {
            $ticketsQuery->where('urgency', $selectedUrgency);
        }

        if ($selectedTechnician === 'unassigned') {
            $ticketsQuery->whereNull('assigned_staff_id');
        } elseif ($selectedTechnician !== 'all') {
            $ticketsQuery->where('assigned_staff_id', (int) $selectedTechnician);
        }

        if ($selectedStatus === 'unassigned') {
            $ticketsQuery
                ->whereNull('assigned_staff_id')
                ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED]);
        } elseif ($selectedStatus === 'open' || $selectedStatus === 'queued') {
            $ticketsQuery
                ->whereNotNull('assigned_staff_id')
                ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED]);
        } elseif ($selectedStatus === 'in_progress') {
            $ticketsQuery->where('status', Ticket::STATUS_IN_PROGRESS);
        } elseif ($selectedStatus === 'resolved') {
            $ticketsQuery->where('status', Ticket::STATUS_RESOLVED);
        } elseif ($selectedStatus === 'closed') {
            $ticketsQuery->where('status', Ticket::STATUS_CLOSED);
        } elseif ($selectedStatus === 'cancelled') {
            $ticketsQuery->where('status', Ticket::STATUS_CANCELLED);
        }

        $ticketsQuery
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($requiresAction, fn ($query) => $query->where(function ($attention) {
                $attention->whereNull('assigned_staff_id')
                    ->orWhereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_CANCELLATION_REQUESTED]);
            }));

        $tickets = $ticketsQuery->get();

        $assignees = $this->assignmentCandidates();
        $nextAutomaticAssignmentAt = app(TicketAssignmentService::class)->nextEligibleShiftAt();

        $filterTechnicians = User::whereIn('role', ['admin', 'technician'])
            ->orderBy('name')
            ->get(['id', 'name', 'disabled_at']);

        $summaryQuery = Ticket::query();

        if ($search !== '') {
            $summaryQuery->where(function ($query) use ($search) {
                $query->where('tracking_number', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }
        if ($selectedUrgency !== 'all') {
            $summaryQuery->where('urgency', $selectedUrgency);
        }
        if ($selectedTechnician === 'unassigned') {
            $summaryQuery->whereNull('assigned_staff_id');
        } elseif ($selectedTechnician !== 'all') {
            $summaryQuery->where('assigned_staff_id', (int) $selectedTechnician);
        }
        $summaryQuery
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($requiresAction, fn ($query) => $query->where(function ($attention) {
                $attention->whereNull('assigned_staff_id')
                    ->orWhereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_CANCELLATION_REQUESTED]);
            }));

        $queueSummary = [
            'unassigned' => (clone $summaryQuery)
                ->whereNull('assigned_staff_id')
                ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])
                ->count(),
            'open' => (clone $summaryQuery)
                ->whereNotNull('assigned_staff_id')
                ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])
                ->count(),
            'in_progress' => (clone $summaryQuery)
                ->whereNotNull('assigned_staff_id')
                ->where('status', Ticket::STATUS_IN_PROGRESS)
                ->count(),
            'resolved' => (clone $summaryQuery)->where('status', Ticket::STATUS_RESOLVED)->count(),
            'closed' => (clone $summaryQuery)->where('status', Ticket::STATUS_CLOSED)->count(),
            'cancelled' => (clone $summaryQuery)->where('status', Ticket::STATUS_CANCELLED)->count(),
        ];

        return view('admin.tickets.index', compact(
            'tickets',
            'assignees',
            'filterTechnicians',
            'queueSummary',
            'selectedStatus',
            'selectedUrgency',
            'selectedTechnician',
            'search',
            'nextAutomaticAssignmentAt',
            'dateFrom',
            'dateTo',
            'requiresAction'
        ));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $requestedAssigneeId = $request->boolean('take_over_ticket')
            ? auth()->id()
            : ($request->has('assigned_staff_id')
                ? ($request->integer('assigned_staff_id') ?: null)
                : ($request->integer('technician_id') ?: null));

        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) use ($request) {
            return $this->updateWithinTransaction($request, $ticket);
        }, $requestedAssigneeId);
    }

    private function updateWithinTransaction(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'assigned_staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'admin_action' => ['nullable', Rule::in(['save', 'close'])],
            'take_over_ticket' => ['sometimes', 'accepted'],
            'urgency' => ['nullable', Rule::in(Ticket::URGENCY_LEVELS)],
            'assignment_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket->loadMissing('assignedStaff', 'technician', 'user');
        $previousStaffId = $ticket->assigned_staff_id ?: $ticket->technician_id;
        $previousStaff = $ticket->assignedStaff ?: $ticket->technician;
        $previousStatus = $ticket->status;
        $previousUrgency = $ticket->urgency;
        $requestedAssigneeId = $request->boolean('take_over_ticket')
            ? auth()->id()
            : ($request->has('assigned_staff_id')
                ? (($validated['assigned_staff_id'] ?? null) ?: null)
                : (($validated['technician_id'] ?? null) ?: null));
        $adminAction = $validated['admin_action'] ?? 'save';
        $reason = trim((string) ($validated['assignment_reason'] ?? ''));

        abort_if($ticket->status === Ticket::STATUS_CANCELLED, 422, 'Cancelled tickets cannot be updated.');
        abort_if($ticket->status === Ticket::STATUS_CANCELLATION_REQUESTED, 422, 'Review the cancellation request before updating this ticket.');
        abort_if($ticket->status === Ticket::STATUS_CLOSED && $adminAction !== 'close', 422, 'Closed tickets cannot be updated.');

        if ($adminAction === 'close') {
            abort_unless($ticket->status === Ticket::STATUS_RESOLVED, 422, 'Only resolved tickets can be closed by admin.');

            $ticket->forceFill(array_merge([
                'status' => Ticket::STATUS_CLOSED,
            ], $ticket->lifecycleAttributes([
                'closed_at' => now(),
            ])))->save();

            $ticket->messages()->create([
                'user_id' => auth()->id(),
                'message' => 'Ticket closed by admin.',
            ]);
        } else {
            abort_if($ticket->status === Ticket::STATUS_RESOLVED, 422, 'Resolved tickets cannot be reassigned. Close or reopen the ticket first.');

            $urgency = $validated['urgency'] ?? $ticket->urgency;
            if ($ticket->urgency_review_required && ! array_key_exists('urgency', $validated)) {
                throw ValidationException::withMessages([
                    'urgency' => 'Choose the urgency before releasing this ticket to the support queue.',
                ]);
            }

            $assignmentService = app(TicketAssignmentService::class);
            $assignee = $requestedAssigneeId
                ? $assignmentService->manualAssignee((int) $requestedAssigneeId)
                : null;
            $assignmentChanged = (int) $previousStaffId !== (int) $requestedAssigneeId;

            if ($previousStaffId && $assignmentChanged && $reason === '') {
                throw ValidationException::withMessages([
                    'assignment_reason' => 'Enter a reason for transferring or releasing this ticket.',
                ]);
            }

            if ($assignmentChanged && $ticket->status === Ticket::STATUS_IN_PROGRESS
                && ! $request->boolean('confirm_in_progress_transfer')) {
                throw ValidationException::withMessages([
                    'confirm_in_progress_transfer' => 'Confirm that you intend to transfer work that is already in progress.',
                ]);
            }

            $warnings = $assignee && $assignmentChanged
                ? $assignmentService->manualAssignmentWarnings($assignee, $ticket)
                : [];

            if ($warnings !== [] && ! $request->boolean('confirm_assignment_override')) {
                throw ValidationException::withMessages([
                    'confirm_assignment_override' => implode(' ', $warnings).' Confirm the override to continue.',
                ]);
            }

            $urgencyChanged = $urgency !== $previousUrgency || $ticket->urgency_review_required;
            $source = $assignee
                ? ($assignee->isAdmin() ? 'admin_takeover' : 'manual')
                : null;
            $storedReason = $reason !== ''
                ? $reason
                : ($assignee ? 'Initial manual assignment by administrator.' : 'Released to the automatic priority queue.');

            $ticket->forceFill([
                'assigned_staff_id' => $assignee?->id,
                'technician_id' => $assignee?->isTechnician() ? $assignee->id : null,
                'status' => $assignee
                    ? ($ticket->status === Ticket::STATUS_IN_PROGRESS ? Ticket::STATUS_IN_PROGRESS : Ticket::STATUS_OPEN)
                    : Ticket::STATUS_QUEUED,
                'urgency' => $urgency,
                'urgency_review_required' => false,
                'triaged_at' => $urgencyChanged ? now() : $ticket->triaged_at,
                'triaged_by' => $urgencyChanged ? auth()->id() : $ticket->triaged_by,
                'response_due_at' => $urgencyChanged
                    ? Ticket::responseDueAtForUrgency($urgency)
                    : ($ticket->response_due_at ?: Ticket::responseDueAtForUrgency($urgency, $ticket->created_at)),
                'assigned_at' => $assignmentChanged ? ($assignee ? now() : null) : $ticket->assigned_at,
                'assignment_source' => $assignmentChanged ? $source : $ticket->assignment_source,
                'assignment_reason' => $assignmentChanged ? $storedReason : $ticket->assignment_reason,
                'assignment_changed_by' => $assignmentChanged ? auth()->id() : $ticket->assignment_changed_by,
                'assignment_unavailable_since' => $assignmentChanged ? null : $ticket->assignment_unavailable_since,
                'appointment_date' => null,
                'appointment_time' => null,
                'appointment_change_reason' => null,
                'appointment_changed_at' => null,
            ])->save();

            if ($assignmentChanged) {
                $assignmentService->recordAssignmentEvent(
                    $ticket,
                    $previousStaffId,
                    $assignee?->id,
                    auth()->id(),
                    $source ?: 'manual_queue',
                    $storedReason
                );
            }

            if (! $ticket->assigned_staff_id) {
                $assignmentService->assign($ticket, auth()->id(), 'automatic_after_triage');
            }

            $ticket->refresh()->load('assignedStaff', 'technician');
            $currentStaff = $ticket->assignedStaff ?: $ticket->technician;
            $finalAssignmentChanged = (int) $previousStaffId !== (int) $ticket->assigned_staff_id;

            if ($finalAssignmentChanged || $urgencyChanged) {
                $message = 'Ticket queue updated. Priority: '.$ticket->displayUrgencyLabel()
                    .'. Assignment: '.($previousStaff?->name ?? 'Unassigned')
                    .' to '.($currentStaff?->name ?? 'Unassigned queue').'.'
                    .($assignmentChanged ? ' Reason: '.$storedReason : '');
                $ticket->messages()->create(['user_id' => auth()->id(), 'message' => $message]);
                system_log('Ticket Queue Updated', $ticket->publicReference().': '.$message);

                if ($ticket->user) {
                    $assignmentLabel = $currentStaff
                        ? $currentStaff->name.' ('.ucfirst($currentStaff->role).')'
                        : 'support queue';
                    AppNotificationSender::send(
                        $ticket->user,
                        'Support ticket updated',
                        $ticket->publicReference().' priority is '.$ticket->displayUrgencyLabel()
                            .'. Current assignment: '.$assignmentLabel.'.',
                        route('customer.tickets.show', $ticket),
                        'ticket_queue_updated',
                        ['ticket_id' => $ticket->id],
                        auth()->id()
                    );
                }
            }

            if ($assignee && $assignmentChanged) {
                AppNotificationSender::send(
                    $assignee,
                    $assignee->isAdmin() ? 'Support ticket taken over' : 'New support ticket assigned',
                    $ticket->publicReference().' ('.$ticket->displayUrgencyLabel().') was assigned to you. Reason: '.$storedReason,
                    route($assignee->isAdmin() ? 'admin.tickets.show' : 'technician.tickets.show', $ticket),
                    $assignee->isAdmin() ? 'ticket_admin_takeover' : 'ticket_assigned',
                    ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference()],
                    auth()->id()
                );

                if ($ticket->user) {
                    CustomerEmailSender::sendTicketAssigned($ticket, $assignee);
                }
            }

            if ($previousStaff && $assignmentChanged && (int) $previousStaff->id !== (int) $assignee?->id
                && ! $previousStaff->isDisabled()) {
                AppNotificationSender::send(
                    $previousStaff,
                    'Support ticket transferred',
                    $ticket->publicReference().' was transferred to '.($currentStaff?->name ?? 'the support queue')
                        .'. Reason: '.$storedReason,
                    route($previousStaff->isAdmin() ? 'admin.tickets.show' : 'technician.tickets.index', $ticket),
                    'ticket_transferred',
                    ['ticket_id' => $ticket->id],
                    auth()->id()
                );
            }
        }

        $ticket->refresh();

        if ($ticket->status !== $previousStatus) {
            system_log(
                $ticket->status === Ticket::STATUS_CLOSED ? 'Ticket Closed' : 'Ticket Status Changed',
                'Ticket '.$ticket->publicReference().' status changed from '
                    .ucfirst(str_replace('_', ' ', $previousStatus)).' to '
                    .ucfirst(str_replace('_', ' ', $ticket->status)).'.'
            );
        }

        if ($ticket->user && $ticket->status === Ticket::STATUS_CLOSED && $ticket->status !== $previousStatus) {
            AppNotificationSender::send(
                $ticket->user,
                'Support ticket updated',
                'Your ticket "'.$ticket->subject.'" is now '.ucfirst(str_replace('_', ' ', $ticket->status)).'.',
                route('customer.tickets.show', $ticket),
                'ticket_status',
                ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference(), 'status' => $ticket->status],
                auth()->id()
            );

            CustomerEmailSender::sendTicketStatusUpdated($ticket);
        }

        $successMessage = match (true) {
            $adminAction === 'close' => 'Ticket closed successfully.',
            $request->boolean('take_over_ticket') => 'Ticket taken over successfully. You are now the assigned handler.',
            default => 'Ticket priority and support assignment updated successfully.',
        };

        return redirect()
            ->route('admin.tickets.index')
            ->with('success', $successMessage);
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(
            'messages.user',
            'user',
            'technician',
            'assignedStaff',
            'assignmentEvents.fromStaff',
            'assignmentEvents.toStaff',
            'assignmentEvents.actor',
            'reopenReviewer'
        );
        $assignees = $this->assignmentCandidates($ticket);

        $nextAutomaticAssignmentAt = $ticket->isQueued()
            ? app(TicketAssignmentService::class)->nextEligibleShiftAt()
            : null;

        return view('admin.tickets.show', compact('ticket', 'assignees', 'nextAutomaticAssignmentAt'));
    }

    public function start(Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) {
            abort_unless((int) $ticket->assigned_staff_id === (int) auth()->id(), 403);
            abort_unless($ticket->isOpenForWork(), 422, 'Only open tickets can be started.');

            $ticket->forceFill(array_merge([
                'status' => Ticket::STATUS_IN_PROGRESS,
            ], $ticket->lifecycleAttributes([
                'started_at' => now(),
            ])))->save();

            $ticket->messages()->create([
                'user_id' => auth()->id(),
                'message' => 'Administrator started work on this ticket.',
            ]);

            system_log(
                'Ticket Status Changed',
                'Ticket '.$ticket->publicReference().' status changed to In progress by '.auth()->user()->name.'.'
            );

            return back()->with('success', 'Ticket work started.');
        });
    }

    public function resolve(Request $request, Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) use ($request) {
            abort_unless((int) $ticket->assigned_staff_id === (int) auth()->id(), 403);
            abort_if($ticket->isCancellationRequested(), 422, 'Review the cancellation request before resolving this ticket.');
            abort_unless($ticket->status === Ticket::STATUS_IN_PROGRESS, 422, 'Start work before resolving this ticket.');

            $validated = $request->validate([
                'resolution_notes' => ['required', 'string', 'max:4000'],
            ]);

            $ticket->forceFill(array_merge([
                'status' => Ticket::STATUS_RESOLVED,
            ], $ticket->lifecycleAttributes([
                'resolved_at' => now(),
                'resolution_notes' => $validated['resolution_notes'],
            ])))->save();

            $ticket->messages()->create([
                'user_id' => auth()->id(),
                'message' => 'Administrator marked this ticket as resolved. Notes: '.$validated['resolution_notes'],
            ]);

            if ($ticket->user) {
                AppNotificationSender::send(
                    $ticket->user,
                    'Support ticket resolved',
                    'Your ticket "'.$ticket->subject.'" has been marked as resolved.',
                    route('customer.tickets.show', $ticket),
                    'ticket_resolved',
                    ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference(), 'status' => $ticket->status],
                    auth()->id()
                );

                CustomerEmailSender::sendTicketStatusUpdated($ticket);
            }

            system_log(
                'Ticket Resolved',
                'Ticket '.$ticket->publicReference().' was resolved by administrator '.auth()->user()->name.'.'
            );

            app(TicketAssignmentService::class)->dispatchQueued(1);

            return back()->with('success', 'Ticket marked as resolved.');
        });
    }

    public function approveCancellation(Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) {
            return $this->approveCancellationWithinTransaction($ticket);
        });
    }

    private function approveCancellationWithinTransaction(Ticket $ticket)
    {
        abort_unless($ticket->status === Ticket::STATUS_CANCELLATION_REQUESTED, 422, 'This ticket is not waiting for cancellation review.');

        $ticket->update([
            'status' => Ticket::STATUS_CANCELLED,
        ]);

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'message' => 'Admin approved ticket cancellation.',
        ]);

        system_log(
            'Ticket Cancellation Approved',
            'Ticket '.$ticket->publicReference().' cancellation was approved by '.auth()->user()->name.'.'
        );

        if ($ticket->user) {
            AppNotificationSender::send(
                $ticket->user,
                'Ticket cancellation approved',
                'Your cancellation request for ticket "'.$ticket->subject.'" was approved.',
                route('customer.tickets.show', $ticket),
                'ticket_cancellation_approved',
                ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference(), 'status' => $ticket->status],
                auth()->id()
            );

            CustomerEmailSender::sendTicketDecision(
                $ticket,
                'Ticket cancellation approved',
                'Your cancellation request was approved. This support ticket is now cancelled and read-only.',
                'Ticket cancellation decision'
            );
        }

        return back()->with('success', 'Ticket cancellation approved.');
    }

    public function rejectCancellation(Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) {
            return $this->rejectCancellationWithinTransaction($ticket);
        });
    }

    private function rejectCancellationWithinTransaction(Ticket $ticket)
    {
        abort_unless($ticket->status === Ticket::STATUS_CANCELLATION_REQUESTED, 422, 'This ticket is not waiting for cancellation review.');

        $ticket->update([
            'status' => Ticket::STATUS_IN_PROGRESS,
        ]);

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'message' => 'Admin rejected cancellation request. Ticket remains In Progress.',
        ]);

        system_log(
            'Ticket Cancellation Rejected',
            'Ticket '.$ticket->publicReference().' cancellation was rejected by '.auth()->user()->name.'.'
        );

        if ($ticket->user) {
            AppNotificationSender::send(
                $ticket->user,
                'Ticket cancellation rejected',
                'Your cancellation request for ticket "'.$ticket->subject.'" was rejected. The ticket remains in progress.',
                route('customer.tickets.show', $ticket),
                'ticket_cancellation_rejected',
                ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference(), 'status' => $ticket->status],
                auth()->id()
            );

            CustomerEmailSender::sendTicketDecision(
                $ticket,
                'Ticket cancellation not approved',
                'Your cancellation request was not approved. Support work on this ticket remains in progress.',
                'Ticket cancellation decision'
            );
        }

        return back()->with('success', 'Ticket cancellation rejected. Ticket remains in progress.');
    }

    public function approveReopenRequest(Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) {
            return $this->approveReopenRequestWithinTransaction($ticket);
        });
    }

    private function approveReopenRequestWithinTransaction(Ticket $ticket)
    {
        abort_unless($ticket->status === Ticket::STATUS_CLOSED, 422, 'Only closed tickets can have reopen requests approved.');
        abort_unless($ticket->hasPendingReopenRequest(), 422, 'This ticket has no pending reopen request.');

        $nextStatus = Ticket::STATUS_OPEN;
        $previousStaffId = $ticket->assigned_staff_id ?: $ticket->technician_id;

        $ticket->forceFill(array_merge([
            'status' => $nextStatus,
            'assigned_staff_id' => null,
            'technician_id' => null,
            'assigned_at' => null,
            'assignment_source' => null,
            'assignment_reason' => 'Administrator approved the customer reopen request.',
            'assignment_changed_by' => auth()->id(),
            'assignment_unavailable_since' => null,
            'appointment_date' => null,
            'appointment_time' => null,
            'appointment_change_reason' => null,
            'appointment_changed_at' => null,
        ], $ticket->lifecycleAttributes([
            'reopen_reviewed_at' => now(),
            'reopen_reviewed_by' => auth()->id(),
        ])))->save();

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'message' => 'Ticket reopen request approved by admin. Returned to the queue for a new technician appointment.',
        ]);

        app(TicketAssignmentService::class)->recordAssignmentEvent(
            $ticket,
            $previousStaffId,
            null,
            auth()->id(),
            'reopen_approved',
            'Administrator approved the customer reopen request.'
        );

        system_log(
            'Ticket Status Changed',
            'Ticket '.$ticket->publicReference().' reopen request was approved and status changed to '.ucfirst(str_replace('_', ' ', $ticket->status)).'.'
        );

        if ($ticket->user) {
            AppNotificationSender::send(
                $ticket->user,
                'Ticket reopen request approved',
                'Your request to reopen ticket "'.$ticket->subject.'" was approved.',
                route('customer.tickets.show', $ticket),
                'ticket_reopen_approved',
                ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference(), 'status' => $ticket->status],
                auth()->id()
            );

            CustomerEmailSender::sendTicketDecision(
                $ticket,
                'Ticket reopen request approved',
                'Your request to reopen this ticket was approved. It has returned to the support queue for assignment.',
                'Ticket reopen decision'
            );
        }

        return back()->with('success', 'Ticket reopen request approved.');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) use ($request) {
            return $this->replyWithinTransaction($request, $ticket);
        });
    }

    private function replyWithinTransaction(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->canReceiveReplies(), 422, 'Closed or cancelled tickets cannot receive new replies.');

        $request->validate([
            'message' => ['nullable', 'required_without_all:ticket_attachment,ticket_attachments', 'string', 'max:4000'],
            'ticket_attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'ticket_attachments' => ['nullable', 'array', 'max:5'],
            'ticket_attachments.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $attachmentPaths = PublicUpload::storeFiles(
            PublicUpload::flattenFiles($request->file('ticket_attachments', []), $request->file('ticket_attachment')),
            'ticket-attachments'
        );

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'message' => trim((string) $request->input('message', '')) ?: 'Attachment uploaded.',
            'attachment_path' => $attachmentPaths[0] ?? null,
            'attachment_paths' => $attachmentPaths,
        ]);

        return back();
    }

    private function assignmentCandidates(?Ticket $ticket = null)
    {
        $service = app(TicketAssignmentService::class);

        return User::query()
            ->whereIn('role', ['admin', 'technician'])
            ->whereNull('disabled_at')
            ->where(function ($query) {
                $query->where('role', 'admin')
                    ->orWhere(function ($technicianQuery) {
                        $technicianQuery->where('role', 'technician')
                            ->where(function ($statusQuery) {
                                $statusQuery->whereNull('technician_status')
                                    ->orWhere('technician_status', '!=', 'on_leave');
                            });
                    });
            })
            ->withCount([
                'assignedTickets as active_ticket_load' => fn ($query) => $query->whereIn('status', Ticket::activeWorkStatuses()),
            ])
            ->orderByRaw("CASE WHEN id = ? THEN 0 WHEN role = 'admin' THEN 1 ELSE 2 END", [auth()->id()])
            ->orderBy('active_ticket_load')
            ->orderBy('name')
            ->get()
            ->each(function (User $staff) use ($service, $ticket): void {
                $staff->setAttribute('assignment_warnings', $service->manualAssignmentWarnings($staff, $ticket));
            });
    }
}
