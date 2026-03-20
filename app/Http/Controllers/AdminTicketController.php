<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
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

        $technicianId = $validated['technician_id'] ?: null;

        if ($technicianId) {
            $technician = User::findOrFail($technicianId);
            abort_unless($technician->isTechnician(), 422);
        }

        $ticket->update($ticket->syncAssignmentStatus($technicianId, $validated['status']));

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
