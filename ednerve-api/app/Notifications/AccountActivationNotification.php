<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountActivationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $user,
        private readonly string $token
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activationUrl = rtrim(config('app.frontend_url'), '/')
            . '/activate-account?token='
            . urlencode($this->token);

        return (new MailMessage)
            ->subject('Activate Your OolaEdNerve Student Account')
            ->greeting('Hello ' . $this->user->name . ',')
            ->line('Your student account has been created successfully.')
            ->line('Please set your password to activate your account.')
            ->action('Activate My Account', $activationUrl)
            ->line('This activation link expires in 24 hours.')
            ->line('If you were not expecting this email, please ignore it.')
            ->salutation('Regards, OolaEdNerve Team');
    }
}
