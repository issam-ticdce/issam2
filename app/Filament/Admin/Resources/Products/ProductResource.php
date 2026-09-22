<?php

namespace App\Filament\Admin\Resources\Products;

use App\Enums\ProductType;
use App\Filament\Admin\Resources\Products\Pages\CreateProduct;
use App\Filament\Admin\Resources\Products\Pages\EditProduct;
use App\Filament\Admin\Resources\Products\Pages\ListProducts;
use App\Filament\Shared\ProductFields;
use App\Filament\Shared\ReviewActions;
use App\Filament\Shared\StatusColumn;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $modelLabel = 'produit / projet';

    protected static ?string $pluralModelLabel = 'produits & projets';

    protected static ?string $navigationLabel = 'Produits & projets';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = Product::awaitingReview()->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Callout::make('Modifications en attente de validation')
                ->description('Le formulaire montre la version publiée. Utilisez « Aperçu » pour voir la proposition de la startup.')
                ->color('warning')
                ->visible(fn (?Product $record) => $record?->isPending() && $record->draft),
            Section::make(__('space.sections.publication'))
                ->columns(3)
                ->schema([
                    Select::make('startup_id')->label('Startup')->relationship('startup', 'name')->searchable()->preload()->required(),
                    Toggle::make('is_published')->label('Publié sur le site'),
                    Toggle::make('is_featured')->label('À la une'),
                ]),
            ...ProductFields::make(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('cover')->label('')->disk('public')
                    ->state(fn (Product $record) => $record->images[0] ?? null)->square(),
                TextColumn::make('name')->label('Nom')->weight('bold')->wrap()
                    ->state(fn (Product $record) => $record->tr('name', 'fr'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%")),
                TextColumn::make('startup.name')->label('Startup')->searchable()->sortable(),
                TextColumn::make('type')->label('Type')->badge(),
                StatusColumn::make(),
                ToggleColumn::make('is_featured')->label('À la une'),
                TextColumn::make('views')->label('Vues')->numeric()->sortable(),
                TextColumn::make('updated_at')->label('Modifié le')->dateTime('d/m/Y')->sortable()->toggleable(),
            ])
            ->filters([
                Filter::make('pending')->label('En attente de validation')
                    ->query(fn (Builder $query) => $query->awaitingReview()),
                TernaryFilter::make('is_published')->label('Publié'),
                SelectFilter::make('type')->label('Type')->options(ProductType::options()),
                SelectFilter::make('startup_id')->label('Startup')->relationship('startup', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ...ReviewActions::make(),
                    DeleteAction::make(),
                ]),
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
