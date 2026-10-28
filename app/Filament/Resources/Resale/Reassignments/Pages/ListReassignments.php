<?php

namespace App\Filament\Resources\Resale\Reassignments\Pages;

use App\Filament\Resources\Resale\Reassignments\ReassignmentResource;
use Filament\Resources\Pages\ListRecords;

class ListReassignments extends ListRecords
{
    protected static string $resource = ReassignmentResource::class;

    public function getTitle(): string
    {
        return 'إعادة تعيين مسؤول الطلب — إعادة البيع';
    }
}
