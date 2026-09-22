<?php

namespace App\Filament\Startup\Resources\Products;

use App\Filament\Shared\ProductFields;
use App\Filament\Shared\ReviewNotice;
use App\Filament\Shared\StatusColumn;
use App\Filament\Startup\Resources\Products\Pages\CreateProduct;
use App\Filament\Startup\Resources\Products\Pages\EditProduct;
use App\Filament\Startup\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Produits et projets de la startup connectée. */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'produits';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('space.nav.products');
    }

    public static function getModelLabel(): string
    {
        return __('space.models.product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('space.models.products');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('startup_id', auth()->user()->startup_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            ReviewNotice::make()->hiddenOn('create'),
            ...ProductFields::make(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ImageColumn::make('cover')->label('')->disk('public')
                    ->state(fn (Product $record) => $record->images[0] ?? null)->square(),
                TextColumn::make('name')->label(__('space.fields.name'))
                    ->state(fn (Product $record) => $record->tr('name'))->weight('bold')->wrap(),
                TextColumn::make('type')->label(__('space.fields.type'))->badge(),
                TextColumn::make('price')->label(__('space.fields.price'))->state(fn (Product $record) => $record->priceLabel()),
                StatusColumn::make(),
                TextColumn::make('views')->label(__('space.fields.views'))->numeric()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
