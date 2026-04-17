<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Support\AppNotificationSender;
use App\Support\CustomerEmailSender;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with(['user', 'technician'])
            ->queueOrdered()
            ->get();

        $technicians = User::where('role', 'technician')->orderBy('name')->get();

        return view('admin.tickets.index', compact('tickets', 'technicians'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,queued,in_progress,resolved,closed',
            'technician_id' => 'nullable|exists:users,id',
        ]);

        $previousTechnicianId = $ticket->technician_id;
        $previousStatus = $ticket->status;
        $technicianId = $validated['technician_id'] ?: null;
        $technician = null;

        if ($technicianId) {
            $technician = User::findOrFail($technicianId);
            abort_unless($technician->isTechnician(), 422);
        }

        $ticket->update($ticket->syncAssignmentStatus($technicianId, $validated['status']));
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

        if ($ticket->user && in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED], true) && $ticket->status !== $previousStatus) {
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
            ->with('success', 'Ticket updated successfully.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('messages.user', 'user', 'technician');
        $technicians = User::where('role', 'technician')->orderBy('name')->get();

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

        if ($ticket->technician_id) {
            $ticket->update($ticket->syncAssignmentStatus($ticket->technician_id, Ticket::STATUS_IN_PROGRESS));
        }

        return back();
    }
}
