<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InquiryType: string implements HasLabel
{
    case Contact = 'contact';
    case Quote = 'quote';
    case Investment = 'investment';

    public function getLabel(): string
    {
        return __('site.inquiry_types.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
