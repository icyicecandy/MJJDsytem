<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Application $application)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'MJJD Telecom Application Rejected - ' . $this->application->reference_no,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-rejected',
            with: [
                'application' => $this->application,
            ],
        );
    }
}
