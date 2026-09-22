<?php

namespace App\Filament\Startup\Resources\Inquiries\Pages;

use App\Filament\Startup\Resources\Inquiries\InquiryResource;
use Filament\Resources\Pages\ListRecords;

class ListInquiries extends ListRecords
{
    protected static string $resource = InquiryResource::class;
}
