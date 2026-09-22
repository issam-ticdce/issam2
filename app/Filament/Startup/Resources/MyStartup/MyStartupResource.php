<?php

namespace App\Filament\Startup\Resources\MyStartup;

use App\Filament\Shared\ReviewNotice;
use App\Filament\Shared\StartupFields;
use App\Filament\Startup\Resources\MyStartup\Pages\EditMyStartup;
use App\Filament\Startup\Resources\MyStartup\Pages\OpenMyStartup;
use App\Models\Startup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/** "Ma startup" : la startup ne voit et ne modifie que sa propre fiche. */
class MyStartupResource extends Resource
{
    protected static ?string $model = Startup::class;

    protected static ?string $slug = 'ma-startup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('space.nav.my_startup');
    }

    public static function getModelLabel(): string
    {
        return __('space.models.startup');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereKey(auth()->user()->startup_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            ReviewNotice::make(),
            ...StartupFields::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => OpenMyStartup::route('/'),
            'edit' => EditMyStartup::route('/{record}'),
        ];
    }
}
