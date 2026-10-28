<?php

namespace App\Filament\Resources\Resale\Requests;

use App\Filament\Resources\Requests\BaseRequestResource;
use App\Filament\Resources\Resale\Requests\Pages\CreateRequest;
use App\Filament\Resources\Resale\Requests\Pages\EditRequest;
use App\Filament\Resources\Resale\Requests\Pages\ListRequests;
use App\Filament\Resources\Resale\Requests\Pages\ViewRequest;
use App\Models\Resale\Request;
use UnitEnum;

class RequestResource extends BaseRequestResource
{
    protected static ?string $model = Request::class;

    protected static ?string $slug = 'resale/requests';

    protected static string|UnitEnum|null $navigationGroup = 'إعادة البيع';

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
