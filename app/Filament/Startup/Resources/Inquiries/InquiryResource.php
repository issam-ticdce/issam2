<?php

namespace App\Filament\Startup\Resources\Inquiries;

use App\Enums\InquiryStatus;
use App\Filament\Shared\InquiryTable;
use App\Filament\Startup\Resources\Inquiries\Pages\ListInquiries;
use App\Models\Inquiry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Demandes reçues par la startup connectée (contact, devis, investissement). */
class InquiryResource extends Resource
{
    protected static ?string $model = Inquiry::class;

    protected static ?string $slug = 'demandes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('space.nav.inquiries');
    }

    public static function getModelLabel(): string
    {
        return __('space.models.inquiry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('space.models.inquiries');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', InquiryStatus::New)->count();

        return $count ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('startup_id', auth()->user()->startup_id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return InquiryTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInquiries::route('/'),
        ];
    }
}
