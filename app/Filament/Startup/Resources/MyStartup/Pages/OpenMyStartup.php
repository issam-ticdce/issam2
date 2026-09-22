<?php

namespace App\Filament\Startup\Resources\MyStartup\Pages;

use App\Filament\Startup\Resources\MyStartup\MyStartupResource;
use Filament\Resources\Pages\Page;

/** Page d'entrée du menu "Ma startup" : redirige vers le formulaire de sa propre fiche. */
class OpenMyStartup extends Page
{
    protected static string $resource = MyStartupResource::class;

    public function mount(): void
    {
        $this->redirect(MyStartupResource::getUrl('edit', ['record' => auth()->user()->startup]));
    }
}
