<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Concerns\ScopesToDepartment;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Shared behaviour for the two customer resources. The concrete resource only
 * names its model and its navigation group, so rental and resale can never
 * drift apart in behaviour (D-020).
 */
abstract class BaseCustomerResource extends Resource
{
    use ScopesToDepartment;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'عميل';

    protected static ?string $pluralModelLabel = 'العملاء';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }
}
