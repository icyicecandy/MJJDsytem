<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyCustomerEmail extends VerifyEmail
{
    /**
     * Build the MJJD Telecom verification email.
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $expiryMinutes = (int) config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Verify your MJJD Telecom email address')
            ->greeting('Welcome to MJJD Telecom, '.$notifiable->name.'!')
            ->line('Confirm this email address to finish securing your customer account.')
            ->action('Verify my email address', $verificationUrl)
            ->line('This secure link expires in '.$expiryMinutes.' minutes.')
            ->line('If you did not create this account, you can safely ignore this message.')
            ->salutation('MJJD Telecom Customer Support');
    }
}
