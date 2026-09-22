<?php

namespace App\Filament\Admin\Resources\Inquiries;

use App\Enums\InquiryStatus;
use App\Filament\Admin\Resources\Inquiries\Pages\ListInquiries;
use App\Filament\Shared\InquiryTable;
use App\Models\Inquiry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Toutes les demandes des visiteurs, toutes startups confondues. */
class InquiryResource extends Resource
{
    protected static ?string $model = Inquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $modelLabel = 'demande';

    protected static ?string $navigationLabel = 'Demandes des visiteurs';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = Inquiry::where('status', InquiryStatus::New)->count();

        return $count ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return InquiryTable::configure($table, admin: true)
            ->pushFilters([
                SelectFilter::make('startup_id')->label('Startup')->relationship('startup', 'name')->searchable()->preload(),
            ])
            ->pushRecordActions([DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInquiries::route('/'),
        ];
    }
}
