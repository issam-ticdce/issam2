<?php

namespace App\Notifications;

use Filament\Facades\Filament;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/** Invitation envoyée par le TICDCE : lien pour choisir son mot de passe. */
class StartupInvitation extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $token = Password::broker()->createToken($notifiable);
        $url = Filament::getPanel('startup')->getResetPasswordUrl($token, $notifiable);

        return (new MailMessage)
            ->subject(__('mail.invitation.subject'))
            ->greeting(__('mail.invitation.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.invitation.line1', ['startup' => $notifiable->startup?->name]))
            ->line(__('mail.invitation.line2'))
            ->action(__('mail.invitation.action'), $url)
            ->line(__('mail.invitation.expire', ['count' => intdiv(config('auth.passwords.users.expire'), 60)]));
    }
}
