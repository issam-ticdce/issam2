<?php

namespace App\Filament\Startup\Resources\Products\Pages;

use App\Filament\Startup\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->record->editableState();
    }

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('space.review.preview'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn () => route('preview.product', $this->record), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
