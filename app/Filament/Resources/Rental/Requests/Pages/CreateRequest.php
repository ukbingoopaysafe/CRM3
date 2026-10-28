<?php

namespace App\Filament\Resources\Rental\Requests\Pages;

use App\Filament\Resources\Rental\Requests\RequestResource;
use App\Filament\Resources\Requests\Concerns\HandlesRequestWrites;
use Filament\Resources\Pages\CreateRecord;

class CreateRequest extends CreateRecord
{
    use HandlesRequestWrites;

    protected static string $resource = RequestResource::class;

    public function getTitle(): string
    {
        return 'استقبال طلب إيجار';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم استقبال الطلب';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
