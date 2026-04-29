<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Support\AppNotificationSender;
use App\Support\CustomerEmailSender;
use App\Support\PublicUpload;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function index(Request $request)
    {
        $selectedStatus = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        if (! in_array($selectedStatus, ['all', 'unassigned', 'open', 'queued', 'in_progress', 'resolved', 'closed', 'cancelled'], true)) {
            $selectedStatus = 'all';
        }

        $ticketsQuery = Ticket::with(['user', 'technician'])
            ->queueOrdered();

        if ($search !== '') {
            $ticketsQuery->where(function ($query) use ($search) {
                $query->where('tracking_number', 'like', '%' . $search . '%')
                    ->orWhere('subject', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
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

        $technicians = User::where('role', 'technician')
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

        $queueSummary = [
            'unassigned' => Ticket::whereNull('technician_id')
                ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])
                ->count(),
            'open' => Ticket::whereNotNull('technician_id')
                ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])
                ->count(),
            'in_progress' => Ticket::whereNotNull('technician_id')
                ->where('status', Ticket::STATUS_IN_PROGRESS)
                ->count(),
            'resolved' => Ticket::where('status', Ticket::STATUS_RESOLVED)->count(),
            'closed' => Ticket::where('status', Ticket::STATUS_CLOSED)->count(),
            'cancelled' => Ticket::where('status', Ticket::STATUS_CANCELLED)->count(),
        ];

        return view('admin.tickets.index', compact('tickets', 'technicians', 'queueSummary', 'selectedStatus', 'search'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'technician_id' => 'nullable|exists:users,id',
            'admin_action' => 'nullable|in:save,close',
        ]);

        $previousTechnicianId = $ticket->technician_id;
        $previousStatus = $ticket->status;
        $technicianId = $validated['technician_id'] ?? null;
        $technicianId = $technicianId ?: null;
        $technician = null;
        $adminAction = $validated['admin_action'] ?? 'save';

        abort_if($ticket->status === Ticket::STATUS_CANCELLED, 422, 'Cancelled tickets cannot be updated.');
        abort_if($ticket->status === Ticket::STATUS_CANCELLATION_REQUESTED, 422, 'Review the cancellation request before updating this ticket.');
        abort_if($ticket->status === Ticket::STATUS_CLOSED && $adminAction !== 'close', 422, 'Closed tickets cannot be updated.');

        if ($technicianId) {
            $technician = User::findOrFail($technicianId);
            abort_unless($technician->isTechnician(), 422);
        }

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
            $nextStatus = match ($ticket->status) {
                Ticket::STATUS_IN_PROGRESS => $technicianId ? Ticket::STATUS_IN_PROGRESS : Ticket::STATUS_OPEN,
                Ticket::STATUS_RESOLVED => Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED => Ticket::STATUS_CLOSED,
                default => Ticket::STATUS_OPEN,
            };

            $ticket->update([
                'technician_id' => $technicianId,
                'status' => $nextStatus,
            ]);
        }

        $ticket->refresh();

        if ($technician && $ticket->technician_id && $ticket->technician_id !== $previousTechnicianId) {
            AppNotificationSender::send(
                $technician,
                'New support ticket assigned',
                'Ticket "' . $ticket->subject . '" was assigned to you.',
                route('technician.tickets.show', $ticket),
                'ticket_assigned',
                ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference()],
                auth()->id()
            );

            system_log(
                $previousTechnicianId ? 'Ticket Reassigned' : 'Ticket Assigned',
                ($previousTechnicianId ? 'Reassigned' : 'Assigned') . ' technician ' . $technician->name . ' to ticket ' . $ticket->publicReference() . '.'
            );
        }

        if ($ticket->status !== $previousStatus) {
            system_log(
                $ticket->status === Ticket::STATUS_CLOSED ? 'Ticket Closed' : 'Ticket Status Changed',
                'Ticket ' . $ticket->publicReference() . ' status changed from ' . ucfirst(str_replace('_', ' ', $previousStatus)) . ' to ' . ucfirst(str_replace('_', ' ', $ticket->status)) . '.'
            );
        }

        if ($ticket->user && $ticket->status === Ticket::STATUS_CLOSED && $ticket->status !== $previousStatus) {
            AppNotificationSender::send(
                $ticket->user,
                'Support ticket updated',
                'Your ticket "' . $ticket->subject . '" is now ' . ucfirst(str_replace('_', ' ', $ticket->status)) . '.',
                route('customer.tickets.show', $ticket),
                'ticket_status',
                ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference(), 'status' => $ticket->status],
                auth()->id()
            );

            CustomerEmailSender::sendTicketStatusUpdated($ticket);
        }

        return redirect()
            ->route('admin.tickets.index')
            ->with('success', $adminAction === 'close' ? 'Ticket closed successfully.' : 'Ticket assignment updated successfully.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('messages.user', 'user', 'technician', 'reopenReviewer');
        $technicians = User::where('role', 'technician')
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
            'Ticket ' . $ticket->publicReference() . ' cancellation was approved by ' . auth()->user()->name . '.'
        );

        if ($ticket->user) {
            AppNotificationSender::send(
                $ticket->user,
                'Ticket cancellation approved',
                'Your cancellation request for ticket "' . $ticket->subject . '" was approved.',
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
            'Ticket ' . $ticket->publicReference() . ' cancellation was rejected by ' . auth()->user()->name . '.'
        );

        if ($ticket->user) {
            AppNotificationSender::send(
                $ticket->user,
                'Ticket cancellation rejected',
                'Your cancellation request for ticket "' . $ticket->subject . '" was rejected. The ticket remains in progress.',
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
        abort_unless($ticket->status === Ticket::STATUS_CLOSED, 422, 'Only closed tickets can have reopen requests approved.');
        abort_unless($ticket->hasPendingReopenRequest(), 422, 'This ticket has no pending reopen request.');

        $nextStatus = $ticket->technician_id ? Ticket::STATUS_IN_PROGRESS : Ticket::STATUS_OPEN;

        $ticket->forceFill(array_merge([
            'status' => $nextStatus,
        ], $ticket->lifecycleAttributes([
            'reopen_reviewed_at' => now(),
            'reopen_reviewed_by' => auth()->id(),
        ])))->save();

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'message' => 'Ticket reopen request approved by admin.',
        ]);

        system_log(
            'Ticket Status Changed',
            'Ticket ' . $ticket->publicReference() . ' reopen request was approved and status changed to ' . ucfirst(str_replace('_', ' ', $ticket->status)) . '.'
        );

        if ($ticket->user) {
            AppNotificationSender::send(
                $ticket->user,
                'Ticket reopen request approved',
                'Your request to reopen ticket "' . $ticket->subject . '" was approved.',
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
