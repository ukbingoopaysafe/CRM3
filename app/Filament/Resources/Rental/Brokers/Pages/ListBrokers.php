<?php

namespace App\Filament\Resources\Rental\Brokers\Pages;

use App\Filament\Resources\Rental\Brokers\BrokerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBrokers extends ListRecords
{
    protected static string $resource = BrokerResource::class;

    public function getTitle(): string
    {
        return 'وسطاء الإيجار';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('وسيط جديد'),
        ];
    }
}
