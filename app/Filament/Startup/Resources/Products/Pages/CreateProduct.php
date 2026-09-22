<?php

namespace App\Filament\Startup\Resources\Products\Pages;

use App\Filament\Startup\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected static bool $canCreateAnother = false;

    /** Un nouveau produit est directement envoyé au TICDCE pour validation. */
    protected function handleRecordCreation(array $data): Model
    {
        $product = new Product(['startup_id' => auth()->user()->startup_id]);
        $product->submitForReview($data);

        return $product;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label(__('space.review.submit'));
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('space.review.submitted');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
