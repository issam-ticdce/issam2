<?php

namespace App\Filament\Admin\Resources\Sectors;

use App\Filament\Admin\Resources\Sectors\Pages\ManageSectors;
use App\Filament\Shared\Translatable;
use App\Models\Sector;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectorResource extends Resource
{
    protected static ?string $model = Sector::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'secteur';

    protected static string|\UnitEnum|null $navigationGroup = 'Paramètres';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Translatable::make('name', 'Nom', fn ($path) => TextInput::make($path)->maxLength(80), required: true),
            TextInput::make('slug')->label('Identifiant (URL)')->required()->alphaDash()->unique(ignoreRecord: true)->maxLength(80),
            TextInput::make('sort')->label('Ordre')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name_fr')->label('Français')->state(fn (Sector $record) => $record->tr('name', 'fr')),
                TextColumn::make('name_ar')->label('العربية')->state(fn (Sector $record) => $record->tr('name', 'ar')),
                TextColumn::make('name_en')->label('English')->state(fn (Sector $record) => $record->tr('name', 'en')),
                TextColumn::make('startups_count')->label('Startups')->counts('startups'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSectors::route('/'),
        ];
    }
}
