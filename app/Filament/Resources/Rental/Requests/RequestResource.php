<?php

namespace App\Filament\Resources\Rental\Requests;

use App\Filament\Resources\Rental\Requests\Pages\CreateRequest;
use App\Filament\Resources\Rental\Requests\Pages\EditRequest;
use App\Filament\Resources\Rental\Requests\Pages\ListRequests;
use App\Filament\Resources\Rental\Requests\Pages\ViewRequest;
use App\Filament\Resources\Requests\BaseRequestResource;
use App\Models\Rental\Request;
use UnitEnum;

class RequestResource extends BaseRequestResource
{
    protected static ?string $model = Request::class;

    protected static ?string $slug = 'rental/requests';

    protected static string|UnitEnum|null $navigationGroup = 'الإيجار';

    public static function getPages(): array
    {
        return [
            'index' => ListRequests::route('/'),
            'create' => CreateRequest::route('/create'),
            'view' => ViewRequest::route('/{record}'),
            'edit' => EditRequest::route('/{record}/edit'),
        ];
    }
}
