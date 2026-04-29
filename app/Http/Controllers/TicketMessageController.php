<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Support\PublicUpload;
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
            'message' => ['nullable', 'required_without:ticket_attachment', 'string'],
            'ticket_attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $attachmentPath = $request->hasFile('ticket_attachment')
            ? PublicUpload::normalizePath($request->file('ticket_attachment')->store('ticket-attachments', 'public'))
            : null;

        $ticket->messages()->create([
            'user_id' => $user->id,
            'message' => trim((string) ($validated['message'] ?? '')) ?: 'Attachment uploaded.',
            'attachment_path' => $attachmentPath,
        ]);

        $ticket->touch();

        return back();
    }
}
