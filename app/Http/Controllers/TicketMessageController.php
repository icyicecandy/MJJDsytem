<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketMessageController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        $user = $request->user();

        $isAllowed = $user->isAdmin()
            || $ticket->user_id === $user->id
            || $ticket->technician_id === $user->id;

        abort_unless($isAllowed, 403);
        abort_unless($ticket->canReceiveReplies(), 422, 'Closed tickets cannot receive new replies.');

        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => $validated['message'],
        ]);

        $ticket->touch();

        return back();
    }
}
