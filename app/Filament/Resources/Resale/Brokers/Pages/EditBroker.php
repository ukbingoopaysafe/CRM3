<?php

namespace App\Filament\Resources\Resale\Brokers\Pages;

use App\Filament\Resources\Brokers\Concerns\HandlesBrokerWrites;
use App\Filament\Resources\Resale\Brokers\BrokerResource;
use Filament\Resources\Pages\EditRecord;

class EditBroker extends EditRecord
{
    use HandlesBrokerWrites;

    protected static string $resource = BrokerResource::class;

    public function getTitle(): string
    {
        return 'تعديل سجل الوسيط';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تم حفظ التعديلات';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
