<?php

namespace App\Filament\Resources\Rental\Deals\Pages;

use App\Filament\Resources\Rental\Deals\DealResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDeal extends ViewRecord
{
    protected static string $resource = DealResource::class;

    public function getTitle(): string
    {
        return 'صفقة #'.$this->getRecord()->getKey();
    }
}
