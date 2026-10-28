<?php

namespace App\Filament\Resources\Rental\Reassignments;

use App\Filament\Resources\Reassignments\BaseReassignmentResource;
use App\Filament\Resources\Rental\Reassignments\Pages\ListReassignments;
use App\Models\Rental\Reassignment;
use UnitEnum;

class ReassignmentResource extends BaseReassignmentResource
{
    protected static ?string $model = Reassignment::class;

    protected static ?string $slug = 'rental/reassignments';

    protected static string|UnitEnum|null $navigationGroup = 'الإيجار';

    public static function getPages(): array
    {
        return [
            'index' => ListReassignments::route('/'),
        ];
    }
}
