<?php

namespace App\Filament\Resources\Areas\Pages;

use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\Areas\Concerns\HandlesAreaWrites;
use Filament\Resources\Pages\CreateRecord;

class CreateArea extends CreateRecord
{
    use HandlesAreaWrites;

    protected static string $resource = AreaResource::class;

    public function getTitle(): string
    {
        return 'إضافة منطقة';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تمت إضافة المنطقة';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
