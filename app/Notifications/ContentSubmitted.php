<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Startup;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Envoyé au TICDCE quand une startup soumet un contenu à valider. */
class ContentSubmitted extends Notification
{
    public function __construct(public Startup|Product $record) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isStartup = $this->record instanceof Startup;
        $startup = $isStartup ? $this->record : $this->record->startup;
        $what = $isStartup ? 'la fiche de la startup' : 'le produit « '.$this->record->tr('name', 'fr').' »';

        $url = $isStartup
            ? route('filament.admin.resources.startups.edit', $this->record)
            : route('filament.admin.resources.products.edit', $this->record);

        return (new MailMessage)
            ->subject("À valider : {$startup->name}")
            ->line("{$startup->name} a soumis {$what} pour validation.")
            ->action('Examiner', $url);
    }
}
