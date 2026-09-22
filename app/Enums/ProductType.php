<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel
{
    case Service = 'service';
    case Application = 'application';
    case Solution = 'solution';
    case Cultural = 'cultural';
    case Project = 'project';
    case Other = 'other';

    public function getLabel(): string
    {
        return __('site.product_types.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
