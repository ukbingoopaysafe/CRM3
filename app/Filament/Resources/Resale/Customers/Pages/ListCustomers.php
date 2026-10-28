<?php

namespace App\Filament\Resources\Resale\Customers\Pages;

use App\Filament\Resources\Resale\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    public function getTitle(): string
    {
        return 'عملاء إعادة البيع';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('عميل جديد'),
        ];
    }
}
