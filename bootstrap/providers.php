<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\StartupPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    StartupPanelProvider::class,
];
