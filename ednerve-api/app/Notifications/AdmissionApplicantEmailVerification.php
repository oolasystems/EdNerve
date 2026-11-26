<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdmissionApplicantEmailVerification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $verificationUrl,
        private readonly string $institutionName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your applicant email address')
            ->greeting('Hello '.$notifiable->full_name.',')
            ->line('You have registered for the admissions portal of '.$this->institutionName.'.')
            ->line('Use the button below to verify your email address. This link expires in 24 hours and can be used only once.')
            ->action('Verify Email Address', $this->verificationUrl)
            ->line('If you did not initiate this registration, you can ignore this email.');
    }
}
