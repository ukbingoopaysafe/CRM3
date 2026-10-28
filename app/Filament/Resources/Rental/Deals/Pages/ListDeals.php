<?php

namespace App\Filament\Resources\Rental\Deals\Pages;

use App\Filament\Resources\Rental\Deals\DealResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeals extends ListRecords
{
    protected static string $resource = DealResource::class;

    public function getTitle(): string
    {
        return 'صفقات الإيجار';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('تسجيل صفقة'),
        ];
    }
}
