<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PriceType: string implements HasLabel
{
    case Quote = 'quote';
    case Fixed = 'fixed';
    case From = 'from';
    case Free = 'free';

    public function getLabel(): string
    {
        return __('site.price_types.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
