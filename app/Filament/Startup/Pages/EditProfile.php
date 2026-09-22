<?php

namespace App\Filament\Startup\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

/** Profil : nom, email, mot de passe et langue de l'espace. */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            Select::make('locale')
                ->label(__('space.fields.locale'))
                ->options(config('ticdce.locale_names'))
                ->required(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function getRedirectUrl(): ?string
    {
        // Recharge la page pour appliquer immédiatement la nouvelle langue.
        return static::getUrl();
    }
}
