<?php

namespace App\Filament\Resources\Rental\Customers\Pages;

use App\Filament\Resources\Customers\Concerns\HandlesCustomerWrites;
use App\Filament\Resources\Rental\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    use HandlesCustomerWrites;

    protected static string $resource = CustomerResource::class;

    public function getTitle(): string
    {
        return 'عميل إيجار جديد';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم إنشاء سجل العميل';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
