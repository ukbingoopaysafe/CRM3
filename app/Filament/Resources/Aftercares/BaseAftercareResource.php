<?php

namespace App\Filament\Resources\Aftercares;

use App\Filament\Concerns\ScopesToDepartment;
use App\Filament\Resources\Aftercares\RelationManagers\AftercareActivitiesRelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Shared aftercare screens. The record is never created by hand: it starts
 * from the first successful deal, or from the customer record for an earlier
 * deal (D-059).
 */
abstract class BaseAftercareResource extends Resource
{
    use ScopesToDepartment;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $modelLabel = 'ما بعد الصفقة';

    protected static ?string $pluralModelLabel = 'ما بعد الصفقة';

    protected static ?int $navigationSort = 50;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AftercareInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AftercaresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AftercareActivitiesRelationManager::class,
        ];
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record === null ? null : 'ما بعد الصفقة #'.$record->getKey();
    }
}
