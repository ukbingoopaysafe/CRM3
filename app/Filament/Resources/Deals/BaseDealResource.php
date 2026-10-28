<?php

namespace App\Filament\Resources\Deals;

use App\Filament\Concerns\ScopesToDepartment;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Shared deal screens for both departments. A deal has no edit page: the
 * record is written once from its request and then kept as it is (D-058).
 */
abstract class BaseDealResource extends Resource
{
    use ScopesToDepartment;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'صفقة';

    protected static ?string $pluralModelLabel = 'الصفقات';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return DealForm::configure($schema, static::getModel()::department());
    }

    public static function infolist(Schema $schema): Schema
    {
        return DealInfolist::configure($schema, static::getModel()::department());
    }

    public static function table(Table $table): Table
    {
        return DealsTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record === null ? null : 'صفقة #'.$record->getKey();
    }
}
