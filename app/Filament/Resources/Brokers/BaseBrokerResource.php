<?php

namespace App\Filament\Resources\Brokers;

use App\Filament\Concerns\ScopesToDepartment;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Shared behaviour for the two broker resources; the concrete class only pins
 * the model and the navigation group (D-020).
 */
abstract class BaseBrokerResource extends Resource
{
    use ScopesToDepartment;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'وسيط';

    protected static ?string $pluralModelLabel = 'الوسطاء';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return BrokerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrokersTable::configure($table);
    }
}
