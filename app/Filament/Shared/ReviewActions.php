<?php

namespace App\Filament\Shared;

use App\Models\Startup;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** Actions de validation pour le TICDCE : aperçu, approuver, refuser. */
class ReviewActions
{
    /** @return array<Action> */
    public static function make(): array
    {
        return [
            Action::make('preview')
                ->label('Aperçu')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (Model $record) => $record instanceof Startup
                    ? route('preview.startup', $record)
                    : route('preview.product', $record), shouldOpenInNewTab: true),

            Action::make('approve')
                ->label('Approuver et publier')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('La version proposée remplacera la version publique et la startup sera prévenue par email.')
                ->visible(fn (Model $record) => $record->isPending())
                ->action(function (Model $record, $livewire) {
                    $record->approve();
                    Notification::make()->title('Publié')->success()->send();
                    self::refresh($livewire);
                }),

            Action::make('reject')
                ->label('Demander des modifications')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (Model $record) => $record->isPending())
                ->schema([
                    Textarea::make('reason')->label('Motif (envoyé à la startup)')->required()->rows(4),
                ])
                ->action(function (Model $record, array $data, $livewire) {
                    $record->reject($data['reason']);
                    Notification::make()->title('La startup a été prévenue')->success()->send();
                    self::refresh($livewire);
                }),
        ];
    }

    /** Sur une page d'édition, recharge la page pour afficher les données publiées à jour. */
    protected static function refresh($livewire): void
    {
        if ($livewire instanceof EditRecord) {
            $livewire->redirect($livewire::getResource()::getUrl('edit', ['record' => $livewire->getRecord()]));
        }
    }
}
