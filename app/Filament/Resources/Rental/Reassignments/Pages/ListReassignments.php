<?php

namespace App\Filament\Resources\Rental\Reassignments\Pages;

use App\Filament\Resources\Rental\Reassignments\ReassignmentResource;
use Filament\Resources\Pages\ListRecords;

class ListReassignments extends ListRecords
{
    protected static string $resource = ReassignmentResource::class;

    public function getTitle(): string
    {
        return 'إعادة تعيين مسؤول الطلب — الإيجار';
    }
}
