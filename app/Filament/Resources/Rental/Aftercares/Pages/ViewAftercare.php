<?php

namespace App\Filament\Resources\Rental\Aftercares\Pages;

use App\Filament\Resources\Aftercares\Concerns\HasAftercareActions;
use App\Filament\Resources\Rental\Aftercares\AftercareResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAftercare extends ViewRecord
{
    use HasAftercareActions;

    protected static string $resource = AftercareResource::class;

    public function getTitle(): string
    {
        return 'ما بعد الصفقة #'.$this->getRecord()->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->recordActivityAction()->record($this->getRecord()),
        ];
    }
}
