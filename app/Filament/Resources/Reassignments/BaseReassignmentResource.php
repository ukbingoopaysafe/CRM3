<?php

namespace App\Filament\Resources\Reassignments;

use App\Filament\Concerns\ScopesToDepartment;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Shared reassignment list. The list is read-only apart from the three
 * decisions; the transfer itself happens when the manager approves (D-027).
 */
abstract class BaseReassignmentResource extends Resource
{
    use ScopesToDepartment;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'إعادة تعيين';

    protected static ?string $pluralModelLabel = 'إعادة التعيين';

    protected static ?int $navigationSort = 35;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return ReassignmentsTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record === null ? null : 'طلب إعادة تعيين #'.$record->getKey();
    }
}
