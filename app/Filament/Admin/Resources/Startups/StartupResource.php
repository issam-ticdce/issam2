<?php

namespace App\Filament\Admin\Resources\Startups;

use App\Filament\Admin\Resources\Startups\Pages\CreateStartup;
use App\Filament\Admin\Resources\Startups\Pages\EditStartup;
use App\Filament\Admin\Resources\Startups\Pages\ListStartups;
use App\Filament\Admin\Resources\Startups\RelationManagers\UsersRelationManager;
use App\Filament\Shared\ReviewActions;
use App\Filament\Shared\StartupFields;
use App\Filament\Shared\StatusColumn;
use App\Models\Sector;
use App\Models\Startup;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
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

class StartupResource extends Resource
{
    protected static ?string $model = Startup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'startup';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = Startup::awaitingReview()->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'En attente de validation';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Callout::make('Modifications en attente de validation')
                ->description('La startup a proposé des modifications. Le formulaire ci-dessous montre la version publiée. Utilisez « Aperçu » pour voir la proposition, puis « Approuver » ou « Demander des modifications ».')
                ->color('warning')
                ->visible(fn (?Startup $record) => $record?->isPending() && $record->draft),
            Section::make(__('space.sections.publication'))
                ->columns(3)
                ->schema([
                    Toggle::make('is_published')->label('Publiée sur le site'),
                    Toggle::make('is_featured')->label('À la une (page d’accueil)'),
                    TextInput::make('slug')->label('Adresse (URL)')->helperText('Laisser vide pour la générer automatiquement.')
                        ->unique(ignoreRecord: true)->alphaDash()->maxLength(120),
                ]),
            ...StartupFields::make(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('logo')->label('')->disk('public')->square(),
                TextColumn::make('name')->label('Nom')->searchable()->sortable()->weight('bold')
                    ->description(fn (Startup $record) => $record->tr('tagline')),
                TextColumn::make('sector_id')->label('Secteur')
                    ->formatStateUsing(fn (Startup $record) => $record->sector?->tr('name'))->toggleable(),
                TextColumn::make('stage')->label('Stade')->badge()->toggleable(),
                StatusColumn::make(),
                ToggleColumn::make('is_featured')->label('À la une'),
                TextColumn::make('products_count')->label('Produits')->counts('products')->sortable(),
                TextColumn::make('views')->label('Vues')->numeric()->sortable(),
                TextColumn::make('updated_at')->label('Modifiée le')->dateTime('d/m/Y')->sortable()->toggleable(),
            ])
            ->filters([
                Filter::make('pending')->label('En attente de validation')
                    ->query(fn (Builder $query) => $query->awaitingReview()),
                TernaryFilter::make('is_published')->label('Publiée'),
                SelectFilter::make('sector_id')->label('Secteur')
                    ->options(fn () => Sector::orderBy('sort')->get()->mapWithKeys(fn ($s) => [$s->id => $s->tr('name', 'fr')])),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ...ReviewActions::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStartups::route('/'),
            'create' => CreateStartup::route('/create'),
            'edit' => EditStartup::route('/{record}/edit'),
        ];
    }
}
