<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvestmentRange: string implements HasLabel
{
    case Under50k = 'under_50k';
    case From50kTo200k = '50k_200k';
    case From200kTo1m = '200k_1m';
    case Over1m = 'over_1m';
    case Undisclosed = 'undisclosed';

    public function getLabel(): string
    {
        return __('site.investment_ranges.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
