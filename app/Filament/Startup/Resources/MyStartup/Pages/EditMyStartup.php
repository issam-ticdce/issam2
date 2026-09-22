<?php

namespace App\Filament\Startup\Resources\MyStartup\Pages;

use App\Filament\Startup\Resources\MyStartup\MyStartupResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditMyStartup extends EditRecord
{
    protected static string $resource = MyStartupResource::class;

    public function getTitle(): string
    {
        return __('space.nav.my_startup');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** Le formulaire affiche la version en attente si elle existe. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->record->editableState();
    }

    /** Enregistrer = envoyer au TICDCE pour validation. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->submitForReview($data);

        return $record;
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label(__('space.review.submit'));
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('space.review.submitted');
    }

    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('space.review.preview'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn () => route('preview.startup', $this->record), shouldOpenInNewTab: true),
            Action::make('public')
                ->label(__('space.review.view_public'))
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('gray')
                ->visible(fn () => $this->record->is_published)
                ->url(fn () => route('startups.show', ['locale' => app()->getLocale(), 'startup' => $this->record]), shouldOpenInNewTab: true),
        ];
    }
}
