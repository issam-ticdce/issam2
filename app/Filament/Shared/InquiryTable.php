<?php

namespace App\Filament\Shared;

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use App\Models\Inquiry;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Liste des demandes des visiteurs (espace startup et administration). */
class InquiryTable
{
    public static function configure(Table $table, bool $admin = false): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns(array_filter([
                TextColumn::make('created_at')->label(__('space.fields.created_at'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('type')->label(__('space.fields.type'))->badge()
                    ->color(fn (InquiryType $state) => match ($state) {
                        InquiryType::Investment => 'success',
                        InquiryType::Quote => 'info',
                        default => 'gray',
                    }),
                $admin ? TextColumn::make('startup.name')->label(__('space.models.startup'))->searchable()->sortable() : null,
                TextColumn::make('name')->label(__('space.fields.visitor'))->searchable()
                    ->description(fn (Inquiry $record) => $record->organization),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('product.name')->label(__('space.models.product'))
                    ->state(fn (Inquiry $record) => $record->product?->tr('name'))->toggleable(),
                TextColumn::make('status')->label(__('space.fields.status'))->badge()
                    ->color(fn (InquiryStatus $state) => match ($state) {
                        InquiryStatus::New => 'warning',
                        InquiryStatus::Answered => 'success',
                        default => 'gray',
                    }),
            ]))
            ->filters([
                SelectFilter::make('type')->label(__('space.fields.type'))->options(InquiryType::options()),
                SelectFilter::make('status')->label(__('space.fields.status'))->options(InquiryStatus::options()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('type')->label(__('space.fields.type'))->badge(),
                            TextEntry::make('created_at')->label(__('space.fields.created_at'))->dateTime('d/m/Y H:i'),
                            TextEntry::make('name')->label(__('site.form.name')),
                            TextEntry::make('email')->label(__('site.form.email'))->copyable(),
                            TextEntry::make('phone')->label(__('site.form.phone'))->placeholder('—'),
                            TextEntry::make('organization')->label(__('site.form.organization'))->placeholder('—'),
                            TextEntry::make('product_name')->label(__('site.form.product'))
                                ->state(fn (Inquiry $record) => $record->product?->tr('name'))->placeholder('—'),
                            TextEntry::make('investment_range')->label(__('site.form.investment_range'))->placeholder('—'),
                            TextEntry::make('message')->label(__('space.fields.message'))->columnSpanFull()
                                ->extraAttributes(['style' => 'white-space: pre-line']),
                        ]),
                    ])
                    ->mountUsing(function (Inquiry $record) {
                        if ($record->status === InquiryStatus::New) {
                            $record->update(['status' => InquiryStatus::Read]);
                        }
                    }),
                Action::make('reply')
                    ->label(__('space.inquiry.reply'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->url(fn (Inquiry $record) => 'mailto:'.$record->email.'?subject='.rawurlencode('TICDCE Marketplace – '.$record->startup->name)),
                Action::make('answered')
                    ->label(__('space.inquiry.mark_answered'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Inquiry $record) => $record->status !== InquiryStatus::Answered)
                    ->action(fn (Inquiry $record) => $record->update(['status' => InquiryStatus::Answered])),
            ]);
    }
}
