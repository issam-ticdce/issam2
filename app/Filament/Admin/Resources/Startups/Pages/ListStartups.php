<?php

namespace App\Filament\Admin\Resources\Startups\Pages;

use App\Filament\Admin\Resources\Startups\StartupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStartups extends ListRecords
{
    protected static string $resource = StartupResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
