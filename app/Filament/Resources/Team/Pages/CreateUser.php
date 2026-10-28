<?php

namespace App\Filament\Resources\Team\Pages;

use App\Filament\Resources\Team\Concerns\HandlesUserWrites;
use App\Filament\Resources\Team\TeamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use HandlesUserWrites;

    protected static string $resource = TeamResource::class;

    public function getTitle(): string
    {
        return 'إضافة حساب';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم إنشاء الحساب';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
