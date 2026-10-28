<?php

namespace App\Filament\Resources\Rental\Aftercares;

use App\Filament\Resources\Aftercares\BaseAftercareResource;
use App\Filament\Resources\Rental\Aftercares\Pages\ListAftercares;
use App\Filament\Resources\Rental\Aftercares\Pages\ViewAftercare;
use App\Models\Rental\Aftercare;
use UnitEnum;

class AftercareResource extends BaseAftercareResource
{
    protected static ?string $model = Aftercare::class;

    protected static ?string $slug = 'rental/aftercares';

    protected static string|UnitEnum|null $navigationGroup = 'الإيجار';

    public static function getPages(): array
    {
        return [
            'index' => ListAftercares::route('/'),
            'view' => ViewAftercare::route('/{record}'),
        ];
    }
}
