<?php

namespace App\Filament\Admin\Resources\Sectors\Pages;

use App\Filament\Admin\Resources\Sectors\SectorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSectors extends ManageRecords
{
    protected static string $resource = SectorResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
