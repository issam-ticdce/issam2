<?php

namespace App\Filament\Startup\Widgets;

use App\Enums\InquiryStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Tableau de bord de la startup. */
class StartupStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $startup = auth()->user()->startup;
        $status = $startup->statusKey();

        return [
            Stat::make(__('space.dashboard.profile_status'), __('space.status.'.$status))
                ->color($startup::statusColor($status))
                ->description($status === 'rejected' ? $startup->rejection_reason : null)
                ->url(route('filament.startup.resources.ma-startup.edit', $startup)),
            Stat::make(__('space.dashboard.published_products'), $startup->products()->published()->count())
                ->url(route('filament.startup.resources.produits.index')),
            Stat::make(__('space.dashboard.new_inquiries'), $startup->inquiries()->where('status', InquiryStatus::New)->count())
                ->url(route('filament.startup.resources.demandes.index')),
            Stat::make(__('space.dashboard.views'), $startup->views),
        ];
    }
}
