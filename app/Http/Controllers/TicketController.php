<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\TicketAssignmentService;
use App\Services\WorkflowTransaction;
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
            Ticket::STATUS_CANCELLATION_REQUESTED => 'Cancellation Requested',
            Ticket::STATUS_RESOLVED => 'Resolved',
            Ticket::STATUS_CLOSED => 'Closed',
            Ticket::STATUS_CANCELLED => 'Cancelled',
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
        return WorkflowTransaction::run(fn () => $this->storeWithinTransaction($request));
    }

    private function storeWithinTransaction(Request $request)
    {
        $request->validate([
            'subject' => 'required|in:'.implode(',', Ticket::subjectKeys()),
            'description' => 'required|string',
            'ticket_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'ticket_photos' => ['nullable', 'array', 'max:5'],
            'ticket_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $subjectKey = $request->subject;
        $urgency = Ticket::resolveUrgencyForSubject($subjectKey);
        $requiresAdminTriage = Ticket::subjectRequiresAdminTriage($subjectKey);
        $photoPaths = PublicUpload::storeFiles(
            PublicUpload::flattenFiles($request->file('ticket_photos', []), $request->file('ticket_photo')),
            'ticket-attachments'
        );
        $photoPath = $photoPaths[0] ?? null;

        $ticket = Ticket::create([
            'user_id' => Auth::id(),
            'subject' => Ticket::resolveSubjectLabel($subjectKey),
            'description' => $request->description,
            'status' => Ticket::STATUS_QUEUED,
            'urgency' => $urgency ?: Ticket::URGENCY_MEDIUM,
            'urgency_review_required' => $requiresAdminTriage,
            'response_due_at' => $requiresAdminTriage
                ? null
                : Ticket::responseDueAtForUrgency($urgency),
            'photo_path' => $photoPath,
            'photo_paths' => $photoPaths,
        ]);

        if (! $requiresAdminTriage) {
            app(TicketAssignmentService::class)->assign($ticket, Auth::id());
            $ticket->refresh();
        }

        AppNotificationSender::sendToRole(
            'admin',
            'New support ticket submitted',
            Auth::user()->name.' opened ticket "'.$ticket->subject.'". '
                .($requiresAdminTriage
                    ? 'Urgency review is required before assignment.'
                    : ($ticket->technician ? 'Automatically assigned to '.$ticket->technician->name.'.' : 'Waiting for an eligible on-shift technician.')),
            route('admin.tickets.show', $ticket),
            'ticket_submitted',
            [
                'ticket_id' => $ticket->id,
                'tracking_number' => $ticket->publicReference(),
            ],
            Auth::id()
        );

        system_log(
            'Ticket Created',
            'Ticket '.$ticket->publicReference().' was created by '.Auth::user()->name.'.'
        );

        return redirect()
            ->route('customer.tickets.index')
            ->with('success', 'Support ticket submitted successfully. Your tracking number is '.$ticket->publicReference().'.');
    }

    public function show(Ticket $ticket)
    {
        abort_unless(Auth::id() === $ticket->user_id, 403);

        $ticket->load('messages.user', 'technician');

        return view('customer.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) use ($request) {
            return $this->replyWithinTransaction($request, $ticket);
        });
    }

    private function replyWithinTransaction(Request $request, Ticket $ticket)
    {
        abort_unless(Auth::id() === $ticket->user_id, 403);
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
            'user_id' => Auth::id(),
            'message' => trim((string) $request->input('message', '')) ?: 'Attachment uploaded.',
            'attachment_path' => $attachmentPaths[0] ?? null,
            'attachment_paths' => $attachmentPaths,
        ]);

        $ticket->touch();

        return back();
    }

    public function cancel(Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) {
            return $this->cancelWithinTransaction($ticket);
        });
    }

    private function cancelWithinTransaction(Ticket $ticket)
    {
        abort_unless(Auth::id() === $ticket->user_id, 403);
        abort_unless($ticket->isOpenForWork(), 422, 'Only open tickets can be cancelled.');

        $ticket->update([
            'status' => Ticket::STATUS_CANCELLED,
        ]);

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => 'Customer cancelled this ticket.',
        ]);

        system_log(
            'Ticket Cancelled',
            'Ticket '.$ticket->publicReference().' was cancelled by '.Auth::user()->name.'.'
        );

        return back()->with('success', 'Ticket cancelled.');
    }

    public function requestCancellation(Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) {
            return $this->requestCancellationWithinTransaction($ticket);
        });
    }

    private function requestCancellationWithinTransaction(Ticket $ticket)
    {
        abort_unless(Auth::id() === $ticket->user_id, 403);
        abort_unless($ticket->status === Ticket::STATUS_IN_PROGRESS, 422, 'Only in-progress tickets can request cancellation.');

        $ticket->update([
            'status' => Ticket::STATUS_CANCELLATION_REQUESTED,
        ]);

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => 'Customer requested ticket cancellation.',
        ]);

        AppNotificationSender::sendToRole(
            'admin',
            'Ticket cancellation requested',
            Auth::user()->name.' requested cancellation for ticket "'.$ticket->subject.'".',
            route('admin.tickets.show', $ticket),
            'ticket_cancellation_requested',
            ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference()],
            Auth::id()
        );

        system_log(
            'Ticket Cancellation Requested',
            'Ticket '.$ticket->publicReference().' cancellation was requested by '.Auth::user()->name.'.'
        );

        return back()->with('success', 'Cancellation request sent for admin review.');
    }

    public function close(Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) {
            return $this->closeWithinTransaction($ticket);
        });
    }

    private function closeWithinTransaction(Ticket $ticket)
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

        system_log(
            'Ticket Closed',
            'Ticket '.$ticket->publicReference().' was closed by '.Auth::user()->name.'.'
        );

        return back()->with('success', 'Ticket closed.');
    }

    public function reopen(Request $request, Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) use ($request) {
            return $this->reopenWithinTransaction($request, $ticket);
        });
    }

    private function reopenWithinTransaction(Request $request, Ticket $ticket)
    {
        abort_unless(Auth::id() === $ticket->user_id, 403);
        abort_unless($ticket->status === Ticket::STATUS_RESOLVED, 422, 'Only resolved tickets can be reopened.');
        abort_if($ticket->hasPendingReopenRequest(), 422, 'A reopen request is already waiting for admin review.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:4000'],
        ]);

        $nextStatus = Ticket::STATUS_OPEN;

        $ticket->forceFill(array_merge([
            'status' => $nextStatus,
            'technician_id' => null,
            'appointment_date' => null,
            'appointment_time' => null,
            'appointment_change_reason' => null,
            'appointment_changed_at' => null,
        ], $ticket->lifecycleAttributes([
            'reopen_requested_at' => now(),
            'reopen_reason' => $validated['reason'],
            'reopen_reviewed_at' => now(),
            'reopen_reviewed_by' => Auth::id(),
        ])))->save();

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => 'Ticket reopened by customer and returned to the queue for a new appointment. Reason: '.$validated['reason'],
        ]);

        AppNotificationSender::sendToRole(
            'admin',
            'Ticket reopened by customer',
            Auth::user()->name.' reopened ticket "'.$ticket->subject.'".',
            route('admin.tickets.show', $ticket),
            'ticket_reopened',
            ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference(), 'status' => $ticket->status],
            Auth::id()
        );

        system_log(
            'Ticket Reopened',
            'Ticket '.$ticket->publicReference().' was reopened by '.Auth::user()->name.'.'
        );

        return back()->with('success', 'Ticket reopened.');
    }
}
