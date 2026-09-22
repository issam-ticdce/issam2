<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InquiryStatus: string implements HasLabel
{
    case New = 'new';
    case Read = 'read';
    case Answered = 'answered';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return __('site.inquiry_statuses.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
