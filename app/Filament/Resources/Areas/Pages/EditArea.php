<?php

namespace App\Filament\Resources\Areas\Pages;

use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\Areas\Concerns\HandlesAreaWrites;
use Filament\Resources\Pages\EditRecord;

class EditArea extends EditRecord
{
    use HandlesAreaWrites;

    protected static string $resource = AreaResource::class;

    public function getTitle(): string
    {
        return 'تعديل المنطقة';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تم حفظ التعديلات';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
