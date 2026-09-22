<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Startup;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Envoyé à la startup quand le TICDCE valide ou refuse son contenu. */
class ContentReviewed extends Notification
{
    public function __construct(public Startup|Product $record, public bool $approved) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->record instanceof Startup ? $this->record->name : $this->record->tr('name');

        $mail = (new MailMessage)
            ->subject(__($this->approved ? 'mail.reviewed.approved_subject' : 'mail.reviewed.rejected_subject', ['title' => $title]))
            ->line(__($this->approved ? 'mail.reviewed.approved_line' : 'mail.reviewed.rejected_line', ['title' => $title]));

        if (! $this->approved) {
            $mail->line(__('mail.reviewed.reason').' '.$this->record->rejection_reason);
        }

        return $mail->action(__('mail.open_space'), route('filament.startup.pages.dashboard'));
    }
}
