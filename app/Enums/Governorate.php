<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Governorate: string implements HasLabel
{
    case Cairo = 'cairo';
    case Giza = 'giza';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cairo => 'القاهرة',
            self::Giza => 'الجيزة',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case) => [$case->value => $case->getLabel()]
        )->all();
    }
}
