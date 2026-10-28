<?php

namespace App\Filament\Resources\Resale\Reassignments;

use App\Filament\Resources\Reassignments\BaseReassignmentResource;
use App\Filament\Resources\Resale\Reassignments\Pages\ListReassignments;
use App\Models\Resale\Reassignment;
use UnitEnum;

class ReassignmentResource extends BaseReassignmentResource
{
    protected static ?string $model = Reassignment::class;

    protected static ?string $slug = 'resale/reassignments';

    protected static string|UnitEnum|null $navigationGroup = 'إعادة البيع';

    public static function getPages(): array
    {
        return [
            'index' => ListReassignments::route('/'),
        ];
    }
}
