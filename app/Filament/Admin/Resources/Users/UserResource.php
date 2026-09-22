<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Notifications\StartupInvitation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Tous les comptes : équipe TICDCE (administrateurs) et membres des startups. */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'compte';

    protected static string|\UnitEnum|null $navigationGroup = 'Paramètres';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(120),
            TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->label('Rôle')->required()->live()
                ->options([User::ROLE_ADMIN => 'Administrateur TICDCE', User::ROLE_STARTUP => 'Membre d’une startup'])
                ->default(User::ROLE_STARTUP),
            Select::make('startup_id')->label('Startup')->relationship('startup', 'name')->searchable()->preload()
                ->visible(fn (Get $get) => $get('role') === User::ROLE_STARTUP)
                ->required(fn (Get $get) => $get('role') === User::ROLE_STARTUP),
            Select::make('locale')->label('Langue')->options(config('ticdce.locale_names'))->default('fr')->required(),
            TextInput::make('password')->label('Mot de passe')->password()->revealable()
                ->helperText('Facultatif : laissez vide et utilisez « Envoyer l’invitation » pour que la personne choisisse le sien.')
                ->dehydrated(fn ($state) => filled($state))->minLength(8),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('role')->label('Rôle')->badge()
                    ->formatStateUsing(fn (string $state) => $state === User::ROLE_ADMIN ? 'Administrateur' : 'Startup')
                    ->color(fn (string $state) => $state === User::ROLE_ADMIN ? 'primary' : 'gray'),
                TextColumn::make('startup.name')->label('Startup')->placeholder('—'),
                TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Rôle')
                    ->options([User::ROLE_ADMIN => 'Administrateur', User::ROLE_STARTUP => 'Startup']),
            ])
            ->recordActions([
                Action::make('invite')
                    ->label('Envoyer l’invitation')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('gray')
                    ->visible(fn (User $record) => $record->role === User::ROLE_STARTUP)
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->notify(new StartupInvitation);
                        Notification::make()->title('Invitation envoyée à '.$record->email)->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make()->hidden(fn (User $record) => $record->is(auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
