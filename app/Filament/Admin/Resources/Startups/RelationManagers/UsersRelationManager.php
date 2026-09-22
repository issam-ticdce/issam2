<?php

namespace App\Filament\Admin\Resources\Startups\RelationManagers;

use App\Models\User;
use App\Notifications\StartupInvitation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** Membres de la startup ayant accès à l'espace startup. */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Comptes de la startup';

    protected static ?string $modelLabel = 'compte';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(120),
            TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
            Select::make('locale')->label('Langue des emails')->options(config('ticdce.locale_names'))->default('fr')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->description('Chaque compte reçoit un email pour choisir son mot de passe et accéder à l’espace startup (/espace).')
            ->columns([
                TextColumn::make('name')->label('Nom'),
                TextColumn::make('email')->label('Email')->copyable(),
                TextColumn::make('locale')->label('Langue')->badge(),
                TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Inviter un membre')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->mutateDataUsing(fn (array $data) => $data + [
                        'role' => User::ROLE_STARTUP,
                        'password' => Str::password(32),
                    ])
                    ->after(function (User $record) {
                        $record->notify(new StartupInvitation);
                        Notification::make()->title('Invitation envoyée à '.$record->email)->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('invite')
                    ->label('Renvoyer l’invitation')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->notify(new StartupInvitation);
                        Notification::make()->title('Invitation envoyée à '.$record->email)->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
