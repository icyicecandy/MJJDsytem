<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TechnicianTicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with('user')
            ->assignedTo(Auth::user())
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status = 'queued' THEN 1 WHEN status = 'open' THEN 2 ELSE 3 END")
            ->oldest('created_at')
            ->get();

        return view('technician.tickets.index', compact('tickets'));
    }

    public function resolve(Ticket $ticket)
    {
        abort_unless($ticket->technician_id === Auth::id(), 403);
        abort_if($ticket->status === Ticket::STATUS_CLOSED, 422, 'Closed tickets cannot be updated.');

        $ticket->update([
            'status' => Ticket::STATUS_RESOLVED,
        ]);

        if ($ticket->user) {
            AppNotificationSender::send(
                $ticket->user,
                'Support ticket resolved',
                'Your ticket "' . $ticket->subject . '" has been marked as resolved.',
                route('customer.tickets.show', $ticket),
                'ticket_resolved',
                ['ticket_id' => $ticket->id, 'status' => $ticket->status],
                Auth::id()
            );
        }

        AppNotificationSender::sendToRole(
            'admin',
            'Ticket resolved by technician',
            Auth::user()->name . ' resolved ticket "' . $ticket->subject . '".',
            route('admin.tickets.show', $ticket),
            'ticket_resolved_admin',
            ['ticket_id' => $ticket->id, 'status' => $ticket->status],
            Auth::id()
        );

        return redirect()
            ->route('technician.tickets.index')
            ->with('success', 'Ticket marked as resolved.');
    }

    public function show(Ticket $ticket)
    {
        abort_unless($ticket->technician_id === Auth::id(), 403);

        $ticket->load('messages.user', 'user');

        return view('technician.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->technician_id === Auth::id(), 403);
        abort_if($ticket->status === Ticket::STATUS_CLOSED, 422, 'Closed tickets cannot receive new replies.');

        $request->validate([
            'message' => 'required|string',
        ]);

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => $request->message,
        ]);

        $ticket->update($ticket->syncAssignmentStatus($ticket->technician_id, Ticket::STATUS_IN_PROGRESS));

        return back();
    }
}

