<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Need: string implements HasLabel
{
    case Funding = 'funding';
    case Partners = 'partners';
    case Clients = 'clients';
    case Distribution = 'distribution';
    case Talents = 'talents';
    case Mentoring = 'mentoring';

    public function getLabel(): string
    {
        return __('site.needs.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
