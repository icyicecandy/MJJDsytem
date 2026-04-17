<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Application $application)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'MJJD Telecom Application Approved - ' . $this->application->reference_no,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-approved',
            with: [
                'application' => $this->application,
            ],
        );
    }
}
