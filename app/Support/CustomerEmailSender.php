<?php

namespace App\Support;

use App\Mail\ApplicationApprovedMail;
use App\Mail\ApplicationRejectedMail;
use App\Mail\ApplicationScheduledMail;
use App\Mail\CustomerWorkflowUpdateMail;
use App\Mail\TicketStatusUpdatedMail;
use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
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

    public static function sendIdentityResubmission(Application $application): void
    {
        self::sendWorkflowUpdate(
            $application->user?->email ?: $application->email,
            'MJJD Telecom ID Resubmission Required - '.$application->reference_no,
            'Replacement ID documents required',
            'The submitted identity documents need attention before your application can proceed. Please review the reason and upload clear replacement documents.',
            $application->reference_no,
            route('customer.application.show'),
            'Review and replace ID',
            'ID resubmission request',
            ['Reason' => $application->id_verification_reason ?: 'Please review the note in your application.'],
            ['application_id' => $application->id]
        );
    }

    public static function sendInstallationRescheduled(Installation $installation, string $heading = 'Installation rescheduled', ?string $message = null, ?string $reason = null): void
    {
        $installation->loadMissing('application.user', 'technician');
        $application = $installation->application;
        if (! $application) {
            return;
        }

        self::sendWorkflowUpdate(
            $application->user?->email ?: $application->email,
            'MJJD Telecom Installation Update - '.$application->reference_no,
            $heading,
            $message ?: 'Your installation schedule has been updated. Please review the confirmed date and time below.',
            $application->reference_no,
            route('customer.application.show'),
            'View installation details',
            'Installation reschedule',
            [
                'Installation date' => $installation->installation_date?->format('F d, Y'),
                'Installation time' => $installation->installation_time ? Installation::slotLabel(\Carbon\Carbon::parse($installation->installation_time)->format('H:i')) : null,
                'Assigned technician' => $installation->technician?->name,
                'Reason' => $reason ?: ($installation->customer_reschedule_reason ?: $installation->reschedule_rejection_reason),
            ],
            ['application_id' => $application->id, 'installation_id' => $installation->id]
        );
    }

    public static function sendInstallationRequiresNewSchedule(Installation $installation): void
    {
        $installation->loadMissing('application.user');
        $application = $installation->application;
        if (! $application) {
            return;
        }

        self::sendWorkflowUpdate(
            $application->user?->email ?: $application->email,
            'MJJD Telecom Installation Schedule Update - '.$application->reference_no,
            'A new installation schedule is required',
            'An operational availability change affected the current appointment. The administrator is arranging a replacement technician or schedule and will send another update after it is confirmed.',
            $application->reference_no,
            route('customer.application.show'),
            'View application status',
            'Installation requires new schedule',
            ['Previous schedule' => $installation->installation_date?->format('F d, Y').' · '.($installation->installation_time ? Installation::slotLabel(\Carbon\Carbon::parse($installation->installation_time)->format('H:i')) : 'Time not recorded')],
            ['application_id' => $application->id, 'installation_id' => $installation->id]
        );
    }

    public static function sendInstallationActivated(Installation $installation): void
    {
        $installation->loadMissing('application.user');
        $application = $installation->application;
        if (! $application) {
            return;
        }

        self::sendWorkflowUpdate(
            $application->user?->email ?: $application->email,
            'MJJD Telecom Service Activated - '.$application->reference_no,
            'Your service has been activated',
            'The installation workflow is complete and your MJJD Telecom service has been marked as activated.',
            $application->reference_no,
            route('customer.application.show'),
            'View application status',
            'Installation activation',
            ['Activation date' => $installation->activation_date?->format('F d, Y')],
            ['application_id' => $application->id, 'installation_id' => $installation->id]
        );
    }

    public static function sendTicketAssigned(Ticket $ticket, User $staff): void
    {
        self::sendWorkflowUpdate(
            $ticket->user?->email,
            'MJJD Telecom Ticket Assigned - '.$ticket->publicReference(),
            'Support staff assigned',
            $staff->name.' ('.ucfirst($staff->role).') is now handling your support ticket. You can follow its progress and communicate through the ticket page.',
            $ticket->publicReference(),
            route('customer.tickets.show', $ticket),
            'View support ticket',
            'Ticket assignment',
            ['Subject' => $ticket->subject, 'Assigned staff' => $staff->name.' ('.ucfirst($staff->role).')'],
            ['ticket_id' => $ticket->id, 'assigned_staff_id' => $staff->id]
        );
    }

    public static function sendTicketDecision(Ticket $ticket, string $heading, string $message, string $label): void
    {
        self::sendWorkflowUpdate(
            $ticket->user?->email,
            'MJJD Telecom Ticket Update - '.$ticket->publicReference(),
            $heading,
            $message,
            $ticket->publicReference(),
            route('customer.tickets.show', $ticket),
            'View support ticket',
            $label,
            ['Subject' => $ticket->subject, 'Status' => $ticket->displayStatusLabel()],
            ['ticket_id' => $ticket->id]
        );
    }

    private static function sendWorkflowUpdate(
        ?string $recipient,
        string $subject,
        string $heading,
        string $message,
        string $reference,
        string $actionUrl,
        string $actionLabel,
        string $deliveryLabel,
        array $details,
        array $context
    ): void {
        self::send(
            $recipient,
            new CustomerWorkflowUpdateMail($subject, $heading, $message, $reference, $actionUrl, $actionLabel, $details),
            $deliveryLabel,
            $context
        );
    }

    private static function send(?string $recipient, Mailable $mail, string $label, array $context): void
    {
        if (! $recipient) {
            return;
        }

        // Never notify a customer about a change that later rolls back.
        DB::afterCommit(function () use ($recipient, $mail, $label, $context) {
            TrackedEmailSender::attempt(
                $recipient,
                $label,
                fn () => Mail::to($recipient)->send($mail),
                $context
            );
        });
    }
}
