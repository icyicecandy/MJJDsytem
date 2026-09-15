<?php

namespace App\Support;

use App\Mail\ApplicationApprovedMail;
use App\Mail\ApplicationRejectedMail;
use App\Mail\ApplicationScheduledMail;
use App\Mail\TicketStatusUpdatedMail;
use App\Models\Application;
use App\Models\Ticket;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CustomerEmailSender
{
    public static function sendApplicationApproved(Application $application): void
    {
        self::send($application->user?->email ?: $application->email,
            new ApplicationApprovedMail($application), 'Application approval',
            ['application_id' => $application->id]);
    }

    public static function sendApplicationRejected(Application $application): void
    {
        self::send($application->user?->email ?: $application->email,
            new ApplicationRejectedMail($application), 'Application rejection',
            ['application_id' => $application->id]);
    }

    public static function sendApplicationScheduled(Application $application): void
    {
        self::send($application->user?->email ?: $application->email,
            new ApplicationScheduledMail($application), 'Application scheduled',
            ['application_id' => $application->id]);
    }

    public static function sendTicketStatusUpdated(Ticket $ticket): void
    {
        self::send($ticket->user?->email, new TicketStatusUpdatedMail($ticket),
            'Ticket status', ['ticket_id' => $ticket->id]);
    }

    private static function send(?string $recipient, Mailable $mail, string $label, array $context): void
    {
        if (! $recipient) {
            return;
        }

        // Never notify a customer about a change that later rolls back.
        DB::afterCommit(function () use ($recipient, $mail, $label, $context) {
            try {
                Mail::to($recipient)->send($mail);
            } catch (\Throwable $exception) {
                Log::warning($label . ' email failed to send.', $context + [
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
}
