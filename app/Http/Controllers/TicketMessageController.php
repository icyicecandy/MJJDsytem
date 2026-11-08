<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\WorkflowTransaction;
use App\Support\PublicUpload;
use Illuminate\Http\Request;

class TicketMessageController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        return WorkflowTransaction::ticket($ticket, function (Ticket $ticket) use ($request) {
            return $this->storeWithinTransaction($request, $ticket);
        });
    }

    private function storeWithinTransaction(Request $request, Ticket $ticket)
    {
        $user = $request->user();

        $isAllowed = $user->isAdmin()
            || $ticket->user_id === $user->id
            || $ticket->assigned_staff_id === $user->id;

        abort_unless($isAllowed, 403);
        abort_unless($ticket->canReceiveReplies(), 422, 'Closed or cancelled tickets cannot receive new replies.');

        $validated = $request->validate([
            'message' => ['nullable', 'required_without_all:ticket_attachment,ticket_attachments', 'string', 'max:4000'],
            'ticket_attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'ticket_attachments' => ['nullable', 'array', 'max:5'],
            'ticket_attachments.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $attachmentPaths = PublicUpload::storeFiles(
            PublicUpload::flattenFiles($request->file('ticket_attachments', []), $request->file('ticket_attachment')),
            'ticket-attachments'
        );

        $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => trim((string) ($validated['message'] ?? '')) ?: 'Attachment uploaded.',
            'attachment_path' => $attachmentPaths[0] ?? null,
            'attachment_paths' => $attachmentPaths,
        ]);

        $ticket->touch();

        return back();
    }
}
