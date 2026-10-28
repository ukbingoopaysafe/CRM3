<?php

namespace App\Filament\Resources\Team\Pages;

use App\Filament\Resources\Team\TeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = TeamResource::class;

    public function getTitle(): string
    {
        return 'فريق العمل';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة حساب'),
        ];
    }
}
