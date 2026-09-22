<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/** Crée (ou promeut) un compte administrateur TICDCE. */
class CreateAdmin extends Command
{
    protected $signature = 'ticdce:admin';

    protected $description = 'Créer un compte administrateur TICDCE';

    public function handle(): int
    {
        $name = text('Nom', required: true);
        $email = text('Email', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Email invalide');
        $password = password('Mot de passe (8 caractères minimum)', required: true, validate: fn ($v) => strlen($v) >= 8 ? null : 'Trop court');

        User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
            'startup_id' => null,
        ]);

        $this->info("Administrateur {$email} prêt. Connexion : ".url('/admin'));

        return self::SUCCESS;
    }
}
