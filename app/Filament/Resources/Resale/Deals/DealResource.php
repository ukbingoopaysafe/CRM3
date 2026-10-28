<?php

namespace App\Filament\Resources\Resale\Deals;

use App\Filament\Resources\Deals\BaseDealResource;
use App\Filament\Resources\Resale\Deals\Pages\CreateDeal;
use App\Filament\Resources\Resale\Deals\Pages\ListDeals;
use App\Filament\Resources\Resale\Deals\Pages\ViewDeal;
use App\Models\Resale\Deal;
use UnitEnum;

class DealResource extends BaseDealResource
{
    protected static ?string $model = Deal::class;

    protected static ?string $slug = 'resale/deals';

    protected static string|UnitEnum|null $navigationGroup = 'إعادة البيع';

    public static function getPages(): array
    {
        return [
            'index' => ListDeals::route('/'),
            'create' => CreateDeal::route('/create'),
            'view' => ViewDeal::route('/{record}'),
        ];
    }
}
