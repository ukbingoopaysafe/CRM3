<?php

namespace App\Filament\Resources\Resale\Brokers;

use App\Filament\Resources\Brokers\BaseBrokerResource;
use App\Filament\Resources\Resale\Brokers\Pages\CreateBroker;
use App\Filament\Resources\Resale\Brokers\Pages\EditBroker;
use App\Filament\Resources\Resale\Brokers\Pages\ListBrokers;
use App\Models\Resale\Broker;
use UnitEnum;

class BrokerResource extends BaseBrokerResource
{
    protected static ?string $model = Broker::class;

    protected static ?string $slug = 'resale/brokers';

    protected static string|UnitEnum|null $navigationGroup = 'إعادة البيع';

    public static function getPages(): array
    {
        return [
            'index' => ListBrokers::route('/'),
            'create' => CreateBroker::route('/create'),
            'edit' => EditBroker::route('/{record}/edit'),
        ];
    }
}
