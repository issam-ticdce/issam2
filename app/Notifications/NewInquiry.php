<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Envoyé à la startup (et au TICDCE en copie) quand un visiteur envoie une demande. */
class NewInquiry extends Notification
{
    public function __construct(public Inquiry $inquiry) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->inquiry;

        $mail = (new MailMessage)
            ->subject(__('mail.inquiry.subject', ['type' => $i->type->getLabel(), 'startup' => $i->startup->name]))
            ->replyTo($i->email, $i->name)
            ->line(__('mail.inquiry.intro', ['type' => $i->type->getLabel()]))
            ->line(__('site.form.name').' : '.$i->name)
            ->line(__('site.form.email').' : '.$i->email);

        if ($i->phone) {
            $mail->line(__('site.form.phone').' : '.$i->phone);
        }
        if ($i->organization) {
            $mail->line(__('site.form.organization').' : '.$i->organization);
        }
        if ($i->product) {
            $mail->line(__('site.form.product').' : '.$i->product->tr('name'));
        }
        if ($i->investment_range) {
            $mail->line(__('site.form.investment_range').' : '.$i->investment_range->getLabel());
        }

        return $mail
            ->line('---')
            ->line($i->message)
            ->line('---')
            ->line(__('mail.inquiry.reply_hint'));
    }
}
