<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\InquiryType;
use App\Filament\Admin\Resources\Inquiries\InquiryResource;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Filament\Admin\Resources\Startups\StartupResource;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\Startup;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Tableau de bord du TICDCE. */
class MarketplaceStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $pending = Startup::awaitingReview()->count() + Product::awaitingReview()->count();
        $month = Inquiry::where('created_at', '>=', now()->startOfMonth());

        return [
            Stat::make('À valider', $pending)
                ->description('Fiches et produits en attente')
                ->color($pending ? 'warning' : 'success')
                ->url(StartupResource::getUrl('index', ['filters' => ['pending' => ['isActive' => true]]])),
            Stat::make('Startups publiées', Startup::published()->count())
                ->description(Startup::count().' au total')
                ->url(StartupResource::getUrl('index')),
            Stat::make('Produits & projets publiés', Product::visible()->count())
                ->url(ProductResource::getUrl('index')),
            Stat::make('Demandes ce mois-ci', (clone $month)->count())
                ->description((clone $month)->where('type', InquiryType::Investment)->count().' intérêt(s) d’investissement')
                ->url(InquiryResource::getUrl('index')),
            Stat::make('Vues des fiches', number_format(Startup::sum('views') + Product::sum('views'), 0, ',', ' ')),
        ];
    }
}
