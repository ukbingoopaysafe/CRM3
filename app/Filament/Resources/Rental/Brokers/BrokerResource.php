<?php

namespace App\Filament\Resources\Rental\Brokers;

use App\Filament\Resources\Brokers\BaseBrokerResource;
use App\Filament\Resources\Rental\Brokers\Pages\CreateBroker;
use App\Filament\Resources\Rental\Brokers\Pages\EditBroker;
use App\Filament\Resources\Rental\Brokers\Pages\ListBrokers;
use App\Models\Rental\Broker;
use UnitEnum;

class BrokerResource extends BaseBrokerResource
{
    protected static ?string $model = Broker::class;

    protected static ?string $slug = 'rental/brokers';

    protected static string|UnitEnum|null $navigationGroup = 'الإيجار';

    public static function getPages(): array
    {
        return [
            'index' => ListBrokers::route('/'),
            'create' => CreateBroker::route('/create'),
            'edit' => EditBroker::route('/{record}/edit'),
        ];
    }
}
