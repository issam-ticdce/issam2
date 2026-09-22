<?php

namespace App\Filament\Shared;

use App\Enums\PriceType;
use App\Enums\ProductType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/** Champs d'un produit / projet, utilisés par l'espace startup et par l'administration. */
class ProductFields
{
    public static function make(): array
    {
        return [
            Section::make(__('space.sections.identity'))
                ->description(__('space.hints.translations'))
                ->schema([
                    Select::make('type')->label(__('space.fields.type'))->options(ProductType::options())->required(),
                    Translatable::make('name', __('space.fields.name'), fn ($path) => TextInput::make($path)->maxLength(150), required: true),
                    Translatable::make('summary', __('space.fields.summary'), fn ($path) => Textarea::make($path)->rows(2)->maxLength(250)),
                    Translatable::make('description', __('space.fields.description'), fn ($path) => Textarea::make($path)->rows(8)->maxLength(8000)),
                ]),

            Section::make(__('space.sections.media'))
                ->schema([
                    FileUpload::make('images')->label(__('space.fields.images'))->helperText(__('space.hints.images'))
                        ->image()->multiple()->reorderable()->appendFiles()->maxFiles(8)->maxSize(4096)
                        ->imageEditor()->disk('public')->directory('products')->visibility('public')->panelLayout('grid'),
                    TextInput::make('video_url')->label(__('space.fields.video_url'))->url()->maxLength(255),
                    TextInput::make('link_url')->label(__('space.fields.link_url'))->url()->maxLength(255),
                ]),

            Section::make(__('space.sections.price'))
                ->columns(2)
                ->schema([
                    Select::make('price_type')->label(__('space.fields.price_type'))
                        ->options(PriceType::options())->default(PriceType::Quote->value)->required()->live(),
                    TextInput::make('price')->label(__('space.fields.price'))->numeric()->minValue(0)
                        ->visible(fn (Get $get) => in_array($get('price_type'), [PriceType::Fixed, PriceType::From, 'fixed', 'from'], true))
                        ->required(fn (Get $get) => in_array($get('price_type'), [PriceType::Fixed, PriceType::From, 'fixed', 'from'], true)),
                    TextInput::make('sort')->label(__('space.fields.sort'))->numeric()->default(0),
                ]),
        ];
    }
}
