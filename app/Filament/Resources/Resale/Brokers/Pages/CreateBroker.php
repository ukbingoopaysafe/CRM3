<?php

namespace App\Filament\Resources\Resale\Brokers\Pages;

use App\Filament\Resources\Brokers\Concerns\HandlesBrokerWrites;
use App\Filament\Resources\Resale\Brokers\BrokerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBroker extends CreateRecord
{
    use HandlesBrokerWrites;

    protected static string $resource = BrokerResource::class;

    public function getTitle(): string
    {
        return 'وسيط إعادة بيع جديد';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم إضافة الوسيط';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
