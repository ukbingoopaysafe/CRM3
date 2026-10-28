<?php

namespace App\Filament\Resources\Resale\Aftercares\Pages;

use App\Filament\Resources\Resale\Aftercares\AftercareResource;
use Filament\Resources\Pages\ListRecords;

class ListAftercares extends ListRecords
{
    protected static string $resource = AftercareResource::class;

    public function getTitle(): string
    {
        return 'ما بعد الصفقة — إعادة البيع';
    }
}
