<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Services\TechnicianSchedule;
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
        $selectedStatus = $request->query('status', 'all');
        $selectedUrgency = strtolower(trim((string) $request->query('urgency', 'all')));
        $selectedTechnician = trim((string) $request->query('technician', 'all'));
        $search = trim((string) $request->query('search', ''));

        if (! in_array($selectedStatus, ['all', 'unassigned', 'open', 'queued', 'in_progress', 'resolved', 'closed', 'cancelled'], true)) {
            $selectedStatus = 'all';
        }
        if (! in_array($selectedUrgency, array_merge(['all'], Ticket::URGENCY_LEVELS), true)) {
            $selectedUrgency = 'all';
        }
        if ($selectedTechnician !== 'all' && $selectedTechnician !== 'unassigned' && ! ctype_digit($selectedTechnician)) {
            $selectedTechnician = 'all';
        }

        $ticketsQuery = Ticket::with(['user', 'technician'])
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
            $ticketsQuery->whereNull('technician_id');
        } elseif ($selectedTechnician !== 'all') {
            $ticketsQuery->where('technician_id', (int) $selectedTechnician);
        }

        if ($selectedStatus === 'unassigned') {
            $ticketsQuery
                ->whereNull('technician_id')
                ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED]);
        } elseif ($selectedStatus === 'open' || $selectedStatus === 'queued') {
            $ticketsQuery
                ->whereNotNull('technician_id')
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

        $tickets = $ticketsQuery->get();

        $technicians = User::where('role', 'technician')->whereNull('disabled_at')
            ->withCount([
                'assignedTickets as active_ticket_load' => fn ($query) => $query->whereIn('status', [
                    Ticket::STATUS_OPEN,
                    Ticket::STATUS_QUEUED,
                    Ticket::STATUS_IN_PROGRESS,
                ]),
            ])
            ->orderBy('active_ticket_load')
            ->orderBy('name')
            ->get();

        $filterTechnicians = User::where('role', 'technician')
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
            $summaryQuery->whereNull('technician_id');
        } elseif ($selectedTechnician !== 'all') {
            $summaryQuery->where('technician_id', (int) $selectedTechnician);
        }

        $queueSummary = [
            'unassigned' => (clone $summaryQuery)
                ->whereNull('technician_id')
                ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])
                ->count(),
            'open' => (clone $summaryQuery)
                ->whereNotNull('technician_id')
                ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])
                ->count(),
            'in_progress' => (clone $summaryQuery)
                ->whereNotNull('technician_id')
                ->where('status', Ticket::STATUS_IN_PROGRESS)
                ->count(),
            'resolved' => (clone $summaryQuery)->where('status', Ticket::STATUS_RESOLVED)->count(),
            'closed' => (clone $summaryQuery)->where('status', Ticket::STATUS_CLOSED)->count(),
            'cancelled' => (clone $summaryQuery)->where('status', Ticket::STATUS_CANCELLED)->count(),
        ];

        return view('admin.tickets.index', compact(
            'tickets',
            'technicians',
            'filterTechnicians',
            'queueSummary',
            'selectedStatus',
            'selectedUrgency',
            'selectedTechnician',
            'search'
        ));
    }

    public function update(Request $request, Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) use ($request) {
            return $this->updateWithinTransaction($request, $ticket);
        }, $request->integer('technician_id') ?: null);
    }

    private function updateWithinTransaction(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'technician_id' => 'nullable|integer|exists:users,id',
            'admin_action' => 'nullable|in:save,close',
            'urgency' => ['nullable', Rule::in(Ticket::URGENCY_LEVELS)],
        ]);

        $previousTechnicianId = $ticket->technician_id;
        $previousTechnician = $ticket->technician;
        $previousStatus = $ticket->status;
        $previousUrgency = $ticket->urgency;
        $technicianId = ($validated['technician_id'] ?? null) ?: null;
        $technician = null;
        $adminAction = $validated['admin_action'] ?? 'save';

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
                    'urgency' => 'Choose the urgency before releasing this ticket to the technician queue.',
                ]);
            }

            if ($technicianId) {
                $technician = TechnicianSchedule::eligibleTechnician((int) $technicianId);
            }


            $urgencyChanged = $urgency !== $previousUrgency || $ticket->urgency_review_required;
            $nextStatus = $technicianId
                ? ($ticket->status === Ticket::STATUS_IN_PROGRESS ? Ticket::STATUS_IN_PROGRESS : Ticket::STATUS_OPEN)
                : Ticket::STATUS_QUEUED;

            $ticket->forceFill([
                'technician_id' => $technicianId,
                'status' => $nextStatus,
                'urgency' => $urgency,
                'urgency_review_required' => false,
                'triaged_at' => $urgencyChanged ? now() : $ticket->triaged_at,
                'triaged_by' => $urgencyChanged ? auth()->id() : $ticket->triaged_by,
                'response_due_at' => $urgencyChanged
                    ? Ticket::responseDueAtForUrgency($urgency)
                    : ($ticket->response_due_at ?: Ticket::responseDueAtForUrgency($urgency, $ticket->created_at)),
                'assigned_at' => $technicianId !== $previousTechnicianId
                    ? ($technicianId ? now() : null)
                    : $ticket->assigned_at,
                'assignment_source' => $technicianId !== $previousTechnicianId
                    ? ($technicianId ? 'manual' : null)
                    : $ticket->assignment_source,
                'appointment_date' => null,
                'appointment_time' => null,
                'appointment_change_reason' => null,
                'appointment_changed_at' => null,
            ])->save();

            $ticket->unsetRelation('technician');

            if (! $ticket->technician_id) {
                $technician = app(TicketAssignmentService::class)->assign($ticket, auth()->id(), 'automatic_after_triage');
                $ticket->refresh()->load('technician');
            } else {
                $ticket->load('technician');
            }

            $assignmentChanged = $previousTechnicianId !== $ticket->technician_id;
            if ($assignmentChanged || $urgencyChanged) {
                $message = 'Ticket queue updated. Priority: '.$ticket->displayUrgencyLabel()
                    .'. Assignment: '.($previousTechnician?->name ?? 'Unassigned')
                    .' to '.($ticket->technician?->name ?? 'Unassigned queue').'.';
                $ticket->messages()->create(['user_id' => auth()->id(), 'message' => $message]);
                system_log('Ticket Queue Updated', $ticket->publicReference().': '.$message);

                if ($ticket->user) {
                    AppNotificationSender::send(
                        $ticket->user,
                        'Support ticket updated',
                        $ticket->publicReference().' priority is '.$ticket->displayUrgencyLabel()
                            .'. Current assignment: '.($ticket->technician?->name ?? 'support queue').'.',
                        route('customer.tickets.show', $ticket),
                        'ticket_queue_updated',
                        ['ticket_id' => $ticket->id],
                        auth()->id()
                    );
                }
            }

            if ($technician && $ticket->technician_id && $ticket->technician_id !== $previousTechnicianId
                && $ticket->assignment_source === 'manual') {
                AppNotificationSender::send(
                    $technician,
                    'New support ticket assigned',
                    $ticket->publicReference().' ('.$ticket->displayUrgencyLabel().') was manually assigned to your queue.',
                    route('technician.tickets.show', $ticket),
                    'ticket_assigned',
                    ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference()],
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

        return redirect()
            ->route('admin.tickets.index')
            ->with('success', $adminAction === 'close'
                ? 'Ticket closed successfully.'
                : 'Ticket priority and queue assignment updated successfully.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('messages.user', 'user', 'technician', 'reopenReviewer');
        $technicians = User::where('role', 'technician')->whereNull('disabled_at')
            ->withCount([
                'assignedTickets as active_ticket_load' => fn ($query) => $query->whereIn('status', [
                    Ticket::STATUS_OPEN,
                    Ticket::STATUS_QUEUED,
                    Ticket::STATUS_IN_PROGRESS,
                ]),
            ])
            ->orderBy('active_ticket_load')
            ->orderBy('name')
            ->get();

        return view('admin.tickets.show', compact('ticket', 'technicians'));
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

        $ticket->forceFill(array_merge([
            'status' => $nextStatus,
            'technician_id' => null,
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
            'message' => ['nullable', 'required_without_all:ticket_attachment,ticket_attachments', 'string'],
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
}
