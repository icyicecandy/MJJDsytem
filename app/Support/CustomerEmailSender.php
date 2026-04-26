<?php

namespace App\Support;

use App\Mail\ApplicationApprovedMail;
use App\Mail\ApplicationRejectedMail;
use App\Mail\ApplicationScheduledMail;
use App\Mail\TicketStatusUpdatedMail;
use App\Models\Application;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CustomerEmailSender
{
    public static function sendApplicationApproved(Application $application): void
    {
        $recipient = $application->user?->email ?: $application->email;
        if (! $recipient) {
            return;
        }

        try {
            Mail::to($recipient)->send(new ApplicationApprovedMail($application));
        } catch (\Throwable $exception) {
            Log::warning('Application approval email failed to send.', [
                'application_id' => $application->id,
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public static function sendApplicationRejected(Application $application): void
    {
        $recipient = $application->user?->email ?: $application->email;
        if (! $recipient) {
            return;
        }

        try {
            Mail::to($recipient)->send(new ApplicationRejectedMail($application));
        } catch (\Throwable $exception) {
            Log::warning('Application rejection email failed to send.', [
                'application_id' => $application->id,
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public static function sendApplicationScheduled(Application $application): void
    {
        $recipient = $application->user?->email ?: $application->email;
        if (! $recipient) {
            return;
        }

        try {
            Mail::to($recipient)->send(new ApplicationScheduledMail($application));
        } catch (\Throwable $exception) {
            Log::warning('Application scheduled email failed to send.', [
                'application_id' => $application->id,
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public static function sendTicketStatusUpdated(Ticket $ticket): void
    {
        $recipient = $ticket->user?->email;
        if (! $recipient) {
            return;
        }

        try {
            Mail::to($recipient)->send(new TicketStatusUpdatedMail($ticket));
        } catch (\Throwable $exception) {
            Log::warning('Ticket status email failed to send.', [
                'ticket_id' => $ticket->id,
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
