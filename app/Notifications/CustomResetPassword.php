<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;


class CustomResetPassword extends ResetPasswordNotification
{
    public function toMail($notifiable)
    {
        $frontendUrl = env('FRONTEND_URL', config('app.url'));

        // Build frontend password reset link
        $resetLink = $frontendUrl . '/reset-password/' . $this->token . '?email=' . urlencode($notifiable->email);

        return (new MailMessage)
                    ->subject('Reset Your Password')
                    ->markdown('emails.reset-password', [
                        'resetLink' => $resetLink,
                        'user' => $notifiable,
                    ]);
    }
}

