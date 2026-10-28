<?php

namespace App\Filament\Resources\Resale\Customers;

use App\Filament\Resources\Customers\BaseCustomerResource;
use App\Filament\Resources\Resale\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Resale\Customers\Pages\EditCustomer;
use App\Filament\Resources\Resale\Customers\Pages\ListCustomers;
use App\Models\Resale\Customer;
use UnitEnum;

class CustomerResource extends BaseCustomerResource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'resale/customers';

    protected static string|UnitEnum|null $navigationGroup = 'إعادة البيع';

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
