<?php

namespace App\Filament\Resources\Rental\Deals;

use App\Filament\Resources\Deals\BaseDealResource;
use App\Filament\Resources\Rental\Deals\Pages\CreateDeal;
use App\Filament\Resources\Rental\Deals\Pages\ListDeals;
use App\Filament\Resources\Rental\Deals\Pages\ViewDeal;
use App\Models\Rental\Deal;
use UnitEnum;

class DealResource extends BaseDealResource
{
    protected static ?string $model = Deal::class;

    protected static ?string $slug = 'rental/deals';

    protected static string|UnitEnum|null $navigationGroup = 'الإيجار';

    public static function getPages(): array
    {
        return [
            'index' => ListDeals::route('/'),
            'create' => CreateDeal::route('/create'),
            'view' => ViewDeal::route('/{record}'),
        ];
    }
}
