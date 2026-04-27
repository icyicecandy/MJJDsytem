<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Support\AppNotificationSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Auth::user()->tickets()->latest()->get();

        return view('customer.tickets.index', compact('tickets'));
    }

    public function create()
    {
        return redirect()->route('customer.tickets.index', ['create_ticket' => 1]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|in:' . implode(',', Ticket::subjectKeys()),
            'description' => 'required|string',
            'ticket_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        $subjectKey = $request->subject;
        $photoPath = $request->hasFile('ticket_photo')
            ? $request->file('ticket_photo')->store('tickets/' . Auth::id(), 'public')
            : null;

        $ticket = Ticket::create([
            'user_id' => Auth::id(),
            'subject' => Ticket::resolveSubjectLabel($subjectKey),
            'description' => $request->description,
            'status' => Ticket::STATUS_OPEN,
            'urgency' => Ticket::resolveUrgencyForSubject($subjectKey),
            'photo_path' => $photoPath,
        ]);

        AppNotificationSender::sendToRole(
            'admin',
            'New support ticket submitted',
            Auth::user()->name . ' opened ticket "' . $ticket->subject . '".',
            route('admin.tickets.show', $ticket),
            'ticket_submitted',
            [
                'ticket_id' => $ticket->id,
                'tracking_number' => $ticket->publicReference(),
            ],
            Auth::id()
        );

        return redirect()
            ->route('customer.tickets.index')
            ->with('success', 'Support ticket submitted successfully. Your tracking number is ' . $ticket->publicReference() . '.');
    }

    public function show(Ticket $ticket)
    {
        abort_unless(Auth::id() === $ticket->user_id, 403);

        $ticket->load('messages.user', 'technician');

        return view('customer.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_unless(Auth::id() === $ticket->user_id, 403);
        abort_unless($ticket->canReceiveReplies(), 422, 'Closed tickets cannot receive new replies.');

        $request->validate([
            'message' => 'required|string',
        ]);

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => $request->message,
        ]);

        $ticket->touch();

        return back();
    }

    public function close(Ticket $ticket)
    {
        abort_unless(Auth::id() === $ticket->user_id, 403);
        abort_unless($ticket->status === Ticket::STATUS_RESOLVED, 422, 'Only resolved tickets can be closed.');

        $ticket->forceFill(array_merge([
            'status' => Ticket::STATUS_CLOSED,
        ], $ticket->lifecycleAttributes([
            'closed_at' => now(),
        ])))->save();

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => 'Ticket closed by customer.',
        ]);

        return back()->with('success', 'Ticket closed.');
    }

    public function reopen(Request $request, Ticket $ticket)
    {
        abort_unless(Auth::id() === $ticket->user_id, 403);
        abort_unless($ticket->status === Ticket::STATUS_RESOLVED, 422, 'Only resolved tickets can be reopened.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:4000'],
        ]);

        $ticket->forceFill([
            'status' => Ticket::STATUS_IN_PROGRESS,
        ])->save();

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => 'Ticket reopened by customer. Reason: ' . $validated['reason'],
        ]);

        return back();
    }
}
