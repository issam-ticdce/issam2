<?php

namespace App\Filament\Admin\Resources\Startups\Pages;

use App\Filament\Admin\Resources\Startups\StartupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStartup extends CreateRecord
{
    protected static string $resource = StartupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        return $data;
    }

    /** Après la création, on arrive sur la fiche pour inviter les membres de la startup. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
