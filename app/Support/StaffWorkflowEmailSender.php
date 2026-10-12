<?php

namespace App\Support;

use App\Mail\StaffWorkflowUpdateMail;
use App\Models\TechnicianUnavailabilityRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class StaffWorkflowEmailSender
{
    public static function sendUnavailabilitySubmitted(TechnicianUnavailabilityRequest $unavailability): void
    {
        $unavailability->loadMissing('technician');
        $admins = User::query()->where('role', 'admin')->whereNull('disabled_at')->get();

        foreach ($admins as $admin) {
            self::send(
                $admin,
                'Technician unavailability needs review',
                $unavailability->technician->name.' submitted an unavailability request that may require installation coverage.',
                $unavailability,
                route('admin.unavailability.show', $unavailability),
                'Review request',
                'Technician unavailability submission'
            );
        }
    }

    public static function sendUnavailabilityDecision(TechnicianUnavailabilityRequest $unavailability): void
    {
        $unavailability->loadMissing('technician');
        $message = match ($unavailability->status) {
            TechnicianUnavailabilityRequest::STATUS_APPROVED => 'Your unavailability request was approved. The scheduling hold remains active for the approved period.',
            TechnicianUnavailabilityRequest::STATUS_CLARIFICATION => 'The administrator requested clarification. Update and resubmit the request from your Availability page.',
            TechnicianUnavailabilityRequest::STATUS_REJECTED => 'Your unavailability request was not approved. Review the administrator note for details.',
            default => 'Your unavailability request was updated.',
        };

        self::send(
            $unavailability->technician,
            'Unavailability request '.$unavailability->statusLabel(),
            $message,
            $unavailability,
            route('technician.unavailability.index'),
            'View availability request',
            'Technician unavailability decision'
        );
    }

    private static function send(User $recipient, string $heading, string $message, TechnicianUnavailabilityRequest $unavailability, string $url, string $button, string $label): void
    {
        $mail = new StaffWorkflowUpdateMail(
            'MJJD Telecom Availability Update - '.$unavailability->reference(),
            $heading,
            $message,
            $unavailability->reference(),
            $url,
            $button,
            [
                'Technician' => $unavailability->technician->name,
                'Period' => $unavailability->effectiveStartAt()->format('M d, Y g:i A').' to '.$unavailability->effectiveEndAt()->format('M d, Y g:i A'),
                'Status' => $unavailability->statusLabel(),
            ],
        );

        DB::afterCommit(function () use ($recipient, $mail, $label, $unavailability): void {
            TrackedEmailSender::attempt(
                $recipient->email,
                $label,
                fn () => Mail::to($recipient->email)->send($mail),
                ['unavailability_request_id' => $unavailability->id],
                $recipient->id
            );
        });
    }
}
