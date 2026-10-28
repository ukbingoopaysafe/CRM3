<?php

namespace App\Filament\Resources\Requests;

use App\Filament\Concerns\ScopesToDepartment;
use App\Filament\Resources\Requests\RelationManagers\ActivitiesRelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Shared behaviour for the two request resources. The concrete resource adds
 * only its model, slug and navigation group, so the workflow cannot diverge
 * between departments (D-020, D-043).
 */
abstract class BaseRequestResource extends Resource
{
    use ScopesToDepartment;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'طلب';

    protected static ?string $pluralModelLabel = 'الطلبات';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return RequestForm::configure($schema, static::getModel()::department());
    }

    public static function infolist(Schema $schema): Schema
    {
        return RequestInfolist::configure($schema, static::getModel()::department());
    }

    public static function table(Table $table): Table
    {
        return RequestsTable::configure($table, static::getModel()::department());
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record === null ? null : 'طلب #'.$record->getKey();
    }
}
