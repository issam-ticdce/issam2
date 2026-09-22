<?php

namespace App\Filament\Shared;

use Filament\Schemas\Components\Callout;
use Illuminate\Database\Eloquent\Model;

/** Bandeau en haut du formulaire : statut de validation du contenu. */
class ReviewNotice
{
    public static function make(): Callout
    {
        return Callout::make(fn (?Model $record) => match ($record?->statusKey()) {
            'pending', 'pending_changes' => __('space.review.pending_title'),
            'rejected' => __('space.review.rejected_title'),
            default => __('space.review.draft_title'),
        })
            ->description(fn (?Model $record) => match ($record?->statusKey()) {
                'pending', 'pending_changes' => __('space.review.pending_text'),
                'rejected' => $record->rejection_reason,
                default => __('space.review.draft_text'),
            })
            ->color(fn (?Model $record) => match ($record?->statusKey()) {
                'rejected' => 'danger',
                'pending', 'pending_changes' => 'warning',
                default => 'info',
            })
            ->columnSpanFull()
            ->visible(fn (?Model $record) => $record?->statusKey() !== 'published');
    }
}
