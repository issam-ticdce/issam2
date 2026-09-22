<?php

namespace App\Filament\Shared;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class StatusColumn
{
    public static function make(): TextColumn
    {
        return TextColumn::make('review_status')
            ->label(__('space.fields.status'))
            ->badge()
            ->state(fn (Model $record) => $record->statusKey())
            ->formatStateUsing(fn (string $state) => __('space.status.'.$state))
            ->color(fn (string $state, Model $record) => $record::statusColor($state));
    }
}
