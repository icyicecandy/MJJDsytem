<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Support\AppNotificationSender;
use App\Support\CustomerEmailSender;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function index(Request $request)
    {
        $selectedStatus = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        if (! in_array($selectedStatus, ['all', 'unassigned', 'queued', 'in_progress', 'resolved', 'closed'], true)) {
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
        } elseif ($selectedStatus === 'queued') {
            $ticketsQuery
                ->whereNotNull('technician_id')
                ->where('status', Ticket::STATUS_QUEUED);
        } elseif ($selectedStatus === 'in_progress') {
            $ticketsQuery->where('status', Ticket::STATUS_IN_PROGRESS);
        } elseif ($selectedStatus === 'resolved') {
            $ticketsQuery->where('status', Ticket::STATUS_RESOLVED);
        } elseif ($selectedStatus === 'closed') {
            $ticketsQuery->where('status', Ticket::STATUS_CLOSED);
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
            'queued' => Ticket::whereNotNull('technician_id')
                ->where('status', Ticket::STATUS_QUEUED)
                ->count(),
            'in_progress' => Ticket::whereNotNull('technician_id')
                ->where('status', Ticket::STATUS_IN_PROGRESS)
                ->count(),
            'resolved' => Ticket::where('status', Ticket::STATUS_RESOLVED)->count(),
            'closed' => Ticket::where('status', Ticket::STATUS_CLOSED)->count(),
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

        if ($technicianId) {
            $technician = User::findOrFail($technicianId);
            abort_unless($technician->isTechnician(), 422);
        }

        if ($adminAction === 'close') {
            abort_unless($ticket->status === Ticket::STATUS_RESOLVED, 422, 'Only resolved tickets can be closed by admin.');

            $ticket->update([
                'status' => Ticket::STATUS_CLOSED,
            ]);
        } else {
            $nextStatus = match ($ticket->status) {
                Ticket::STATUS_IN_PROGRESS => $technicianId ? Ticket::STATUS_IN_PROGRESS : Ticket::STATUS_QUEUED,
                Ticket::STATUS_RESOLVED => Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED => Ticket::STATUS_CLOSED,
                default => Ticket::STATUS_QUEUED,
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
        $ticket->load('messages.user', 'user', 'technician');
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

    public function reply(Request $request, Ticket $ticket)
    {
        abort_if($ticket->status === Ticket::STATUS_CLOSED, 422, 'Closed tickets cannot receive new replies.');

        $request->validate([
            'message' => 'required|string',
        ]);

        $ticket->messages()->create([
            'user_id' => auth()->id(),
            'message' => $request->message,
        ]);

        return back();
    }
}
