<?php

namespace App\Filament\Resources\Rental\Customers;

use App\Filament\Resources\Customers\BaseCustomerResource;
use App\Filament\Resources\Rental\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Rental\Customers\Pages\EditCustomer;
use App\Filament\Resources\Rental\Customers\Pages\ListCustomers;
use App\Models\Rental\Customer;
use UnitEnum;

class CustomerResource extends BaseCustomerResource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'rental/customers';

    protected static string|UnitEnum|null $navigationGroup = 'الإيجار';

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
