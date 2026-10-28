<?php

namespace App\Filament\Resources\Resale\Brokers\Pages;

use App\Filament\Resources\Resale\Brokers\BrokerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBrokers extends ListRecords
{
    protected static string $resource = BrokerResource::class;

    public function getTitle(): string
    {
        return 'وسطاء إعادة البيع';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('وسيط جديد'),
        ];
    }
}
