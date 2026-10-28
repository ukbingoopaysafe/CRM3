<?php

namespace App\Filament\Resources\Resale\Requests\Pages;

use App\Filament\Resources\Resale\Requests\RequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRequests extends ListRecords
{
    protected static string $resource = RequestResource::class;

    public function getTitle(): string
    {
        return 'طلبات إعادة البيع';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('طلب جديد'),
        ];
    }
}
