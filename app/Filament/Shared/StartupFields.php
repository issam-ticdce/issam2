<?php

namespace App\Filament\Shared;

use App\Enums\Need;
use App\Enums\Stage;
use App\Models\Sector;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/** Champs de la fiche startup, utilisés par l'espace startup et par l'administration. */
class StartupFields
{
    public static function make(): array
    {
        return [
            Section::make(__('space.sections.identity'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label(__('space.fields.startup_name'))->required()->maxLength(120)->columnSpanFull(),
                    FileUpload::make('logo')->label(__('space.fields.logo'))->helperText(__('space.hints.logo'))
                        ->image()->imageEditor()->disk('public')->directory('startups/logos')->visibility('public')->maxSize(2048),
                    FileUpload::make('cover')->label(__('space.fields.cover'))->helperText(__('space.hints.cover'))
                        ->image()->imageEditor()->disk('public')->directory('startups/covers')->visibility('public')->maxSize(4096),
                ]),

            Section::make(__('space.sections.presentation'))
                ->description(__('space.hints.translations'))
                ->schema([
                    Translatable::make('tagline', __('space.fields.tagline'), fn ($path) => TextInput::make($path)->maxLength(160)),
                    Translatable::make('description', __('space.fields.description'), fn ($path) => Textarea::make($path)->rows(8)->maxLength(5000), required: true),
                ]),

            Section::make(__('space.sections.details'))
                ->columns(2)
                ->schema([
                    Select::make('sector_id')->label(__('space.fields.sector'))
                        ->options(fn () => Sector::orderBy('sort')->get()->mapWithKeys(fn ($s) => [$s->id => $s->tr('name')]))
                        ->searchable(),
                    Select::make('stage')->label(__('space.fields.stage'))->options(Stage::options()),
                    TextInput::make('founded_year')->label(__('space.fields.founded_year'))->numeric()->minValue(1950)->maxValue((int) date('Y')),
                    TextInput::make('city')->label(__('space.fields.city'))->maxLength(80),
                ]),

            Section::make(__('space.sections.contact'))
                ->columns(2)
                ->schema([
                    TextInput::make('email')->label(__('space.fields.email'))->email()->maxLength(190),
                    TextInput::make('phone')->label(__('space.fields.phone'))->tel()->maxLength(40),
                    TextInput::make('website')->label(__('space.fields.website'))->url()->maxLength(255),
                    TextInput::make('video_url')->label(__('space.fields.video_url'))->url()->maxLength(255),
                    TextInput::make('facebook')->label(__('space.fields.facebook'))->url()->maxLength(255),
                    TextInput::make('linkedin')->label(__('space.fields.linkedin'))->url()->maxLength(255),
                    TextInput::make('instagram')->label(__('space.fields.instagram'))->url()->maxLength(255),
                ]),

            Section::make(__('space.sections.needs'))
                ->schema([
                    CheckboxList::make('needs')->label(__('space.fields.needs'))->options(Need::options())->columns(3),
                    Translatable::make('needs_details', __('space.fields.needs_details'), fn ($path) => Textarea::make($path)->rows(3)->maxLength(1000)),
                ]),

            Section::make(__('space.sections.team'))
                ->collapsible()
                ->schema([
                    Repeater::make('team')->hiddenLabel()
                        ->addActionLabel(__('space.fields.add_member'))
                        ->columns(3)
                        ->reorderable()
                        ->maxItems(20)
                        ->defaultItems(0)
                        ->schema([
                            TextInput::make('name')->label(__('space.fields.member_name'))->required()->maxLength(100),
                            TextInput::make('role')->label(__('space.fields.member_role'))->maxLength(100),
                            FileUpload::make('photo')->label(__('space.fields.member_photo'))
                                ->image()->avatar()->disk('public')->directory('startups/team')->visibility('public')->maxSize(1024),
                        ]),
                ]),
        ];
    }
}
