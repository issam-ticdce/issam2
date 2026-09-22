<?php

namespace App\Filament\Admin\Resources\Startups\Pages;

use App\Filament\Admin\Resources\Startups\StartupResource;
use App\Filament\Shared\ReviewActions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditStartup extends EditRecord
{
    protected static string $resource = StartupResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['slug'] ?? null)) {
            $data['slug'] = $this->record->slug;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...ReviewActions::make(),
            Action::make('public')
                ->label('Page publique')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('gray')
                ->visible(fn () => $this->record->is_published)
                ->url(fn () => route('startups.show', ['locale' => 'fr', 'startup' => $this->record]), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
