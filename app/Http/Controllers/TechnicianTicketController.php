<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\WorkflowTransaction;
use App\Support\AppNotificationSender;
use App\Support\CustomerEmailSender;
use App\Support\PublicUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TechnicianTicketController extends Controller
{
    public function index(Request $request)
    {
        $selectedStatus = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        if (! in_array($selectedStatus, ['all', 'open', 'queued', 'in_progress', 'resolved', 'closed', 'cancelled'], true)) {
            $selectedStatus = 'all';
        }

        $ticketsQuery = Ticket::with('user')
            ->assignedTo(Auth::user())
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status IN ('open', 'queued') THEN 1 ELSE 3 END")
            ->oldest('created_at');

        if ($search !== '') {
            $ticketsQuery->where(function ($query) use ($search) {
                $query->where('tracking_number', 'like', '%' . $search . '%')
                    ->orWhere('subject', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($selectedStatus === 'open' || $selectedStatus === 'queued') {
            $ticketsQuery->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED]);
        } elseif ($selectedStatus !== 'all') {
            $ticketsQuery->where('status', $selectedStatus);
        }

        $tickets = $ticketsQuery->get();

        $queueSummary = [
            'open' => Ticket::assignedTo(Auth::user())->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])->count(),
            'in_progress' => Ticket::assignedTo(Auth::user())->where('status', Ticket::STATUS_IN_PROGRESS)->count(),
            'resolved' => Ticket::assignedTo(Auth::user())->where('status', Ticket::STATUS_RESOLVED)->count(),
            'closed' => Ticket::assignedTo(Auth::user())->where('status', Ticket::STATUS_CLOSED)->count(),
            'cancelled' => Ticket::assignedTo(Auth::user())->where('status', Ticket::STATUS_CANCELLED)->count(),
        ];

        return view('technician.tickets.index', compact('tickets', 'queueSummary', 'selectedStatus', 'search'));
    }

    public function start(Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) {
            return $this->startWithinTransaction($ticket);
        });
    }

    private function startWithinTransaction(Ticket $ticket)
    {
        abort_unless($ticket->technician_id === Auth::id(), 403);
        abort_unless($ticket->isOpenForWork(), 422, 'Only open tickets can be started.');

        $ticket->forceFill(array_merge([
            'status' => Ticket::STATUS_IN_PROGRESS,
        ], $ticket->lifecycleAttributes([
            'started_at' => now(),
        ])))->save();

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => 'Technician started work on this ticket.',
        ]);

        system_log(
            'Ticket Status Changed',
            'Ticket ' . $ticket->publicReference() . ' status changed to In progress by ' . Auth::user()->name . '.'
        );

        return back()->with('success', 'Ticket work started.');
    }

    public function resolve(Request $request, Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) use ($request) {
            return $this->resolveWithinTransaction($request, $ticket);
        });
    }

    private function resolveWithinTransaction(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->technician_id === Auth::id(), 403);

        if ($ticket->status === Ticket::STATUS_RESOLVED) {
            return redirect()
                ->route('technician.tickets.show', $ticket)
                ->with('success', 'Ticket is already marked as resolved.');
        }

        if ($ticket->isCancellationRequested()) {
            return back()
                ->withInput()
                ->with('error', 'Customer requested cancellation. Waiting for admin decision.');
        }

        if ($ticket->status !== Ticket::STATUS_IN_PROGRESS) {
            return back()
                ->withInput()
                ->with('error', 'Start work on this ticket before marking it as resolved.');
        }

        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:4000'],
        ]);

        $ticket->forceFill(array_merge([
            'status' => Ticket::STATUS_RESOLVED,
        ], $ticket->lifecycleAttributes([
            'resolved_at' => now(),
            'resolution_notes' => $validated['resolution_notes'],
        ])))->save();

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => 'Technician marked this ticket as resolved. Notes: ' . $validated['resolution_notes'],
        ]);

        $ticket->refresh();

        if ($ticket->user) {
            AppNotificationSender::send(
                $ticket->user,
                'Support ticket resolved',
                'Your ticket "' . $ticket->subject . '" has been marked as resolved.',
                route('customer.tickets.show', $ticket),
                'ticket_resolved',
                ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference(), 'status' => $ticket->status],
                Auth::id()
            );

            CustomerEmailSender::sendTicketStatusUpdated($ticket);
        }

        AppNotificationSender::sendToRole(
            'admin',
            'Ticket resolved by technician',
            Auth::user()->name . ' resolved ticket "' . $ticket->subject . '".',
            route('admin.tickets.show', $ticket),
            'ticket_resolved_admin',
            ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference(), 'status' => $ticket->status],
            Auth::id()
        );

        system_log(
            'Ticket Resolved',
            'Ticket ' . $ticket->publicReference() . ' was resolved by ' . Auth::user()->name . '.'
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
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) use ($request) {
            return $this->replyWithinTransaction($request, $ticket);
        });
    }

    private function replyWithinTransaction(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->technician_id === Auth::id(), 403);
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
}
