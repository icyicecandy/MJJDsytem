<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TechnicianAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $technician,
        public string $temporaryPassword
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'MJJD Telecom Technician Account Access',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.technician-account-created',
            with: [
                'technician' => $this->technician,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => url('/'),
            ],
        );
    }
}
