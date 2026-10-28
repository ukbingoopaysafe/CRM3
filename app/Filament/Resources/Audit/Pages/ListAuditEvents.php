<?php

namespace App\Filament\Resources\Audit\Pages;

use App\Filament\Resources\Audit\AuditEventResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditEvents extends ListRecords
{
    protected static string $resource = AuditEventResource::class;

    public function getTitle(): string
    {
        return 'سجل التدقيق';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
