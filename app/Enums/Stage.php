<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Stage: string implements HasLabel
{
    case Idea = 'idea';
    case Prototype = 'prototype';
    case Mvp = 'mvp';
    case Market = 'market';
    case Growth = 'growth';

    public function getLabel(): string
    {
        return __('site.stages.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
