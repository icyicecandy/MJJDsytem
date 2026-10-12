<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerWorkflowUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $heading,
        public string $messageText,
        public string $reference,
        public string $actionUrl,
        public string $actionLabel = 'View details',
        public array $details = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-workflow-update',
            with: [
                'heading' => $this->heading,
                'messageText' => $this->messageText,
                'reference' => $this->reference,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
                'details' => $this->details,
            ],
        );
    }
}
