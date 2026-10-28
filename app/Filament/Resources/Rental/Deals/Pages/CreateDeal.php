<?php

namespace App\Filament\Resources\Rental\Deals\Pages;

use App\Filament\Resources\Deals\Concerns\HandlesDealWrites;
use App\Filament\Resources\Rental\Deals\DealResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeal extends CreateRecord
{
    use HandlesDealWrites;

    protected static string $resource = DealResource::class;

    public function getTitle(): string
    {
        return 'تسجيل صفقة إيجار';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم تسجيل الصفقة';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
