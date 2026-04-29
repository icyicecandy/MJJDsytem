<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Support\AppNotificationSender;
use App\Support\PublicUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $selectedStatus = trim((string) $request->query('status', ''));
        $statusOptions = [
            Ticket::STATUS_OPEN => 'Open',
            Ticket::STATUS_IN_PROGRESS => 'In Progress',
            Ticket::STATUS_RESOLVED => 'Resolved',
            Ticket::STATUS_CLOSED => 'Closed',
        ];

        if ($selectedStatus !== '' && ! array_key_exists($selectedStatus, $statusOptions)) {
            $selectedStatus = '';
        }

        $ticketsQuery = Auth::user()
            ->tickets()
            ->with('technician')
            ->when($selectedStatus !== '', function ($query) use ($selectedStatus) {
                if ($selectedStatus === Ticket::STATUS_OPEN) {
                    $query->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED]);

                    return;
                }

                $query->where('status', $selectedStatus);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('tracking_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('technician', function ($technicianQuery) use ($search) {
                            $technicianQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest();

        $tickets = $ticketsQuery->get();

        return view('customer.tickets.index', compact('tickets', 'search', 'selectedStatus', 'statusOptions'));
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
            'ticket_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $subjectKey = $request->subject;
        $photoPath = $request->hasFile('ticket_photo')
            ? PublicUpload::normalizePath($request->file('ticket_photo')->store('ticket-attachments', 'public'))
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
            'message' => ['nullable', 'required_without:ticket_attachment', 'string'],
            'ticket_attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $attachmentPath = $request->hasFile('ticket_attachment')
            ? PublicUpload::normalizePath($request->file('ticket_attachment')->store('ticket-attachments', 'public'))
            : null;

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => trim((string) $request->input('message', '')) ?: 'Attachment uploaded.',
            'attachment_path' => $attachmentPath,
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
