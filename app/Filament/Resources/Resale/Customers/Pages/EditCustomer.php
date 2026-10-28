<?php

namespace App\Filament\Resources\Resale\Customers\Pages;

use App\Filament\Resources\Customers\Concerns\HandlesCustomerWrites;
use App\Filament\Resources\Resale\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    use HandlesCustomerWrites;

    protected static string $resource = CustomerResource::class;

    public function getTitle(): string
    {
        return 'تعديل سجل العميل';
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
