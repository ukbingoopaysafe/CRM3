<?php

namespace App\Filament\Resources\Rental\Customers\Pages;

use App\Filament\Concerns\HasExcelActions;
use App\Filament\Resources\Rental\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    use HasExcelActions;

    protected static string $resource = CustomerResource::class;

    public function getTitle(): string
    {
        return 'عملاء الإيجار';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('عميل جديد'),
            $this->customerExcelActions(),
        ];
    }
}
