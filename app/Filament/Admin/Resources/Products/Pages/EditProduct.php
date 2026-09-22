<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Resources\Products\ProductResource;
use App\Filament\Shared\ReviewActions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ReviewActions::make(),
            Action::make('public')
                ->label('Page publique')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('gray')
                ->visible(fn () => $this->record->is_published && $this->record->startup->is_published)
                ->url(fn () => route('products.show', ['locale' => 'fr', 'product' => $this->record]), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
