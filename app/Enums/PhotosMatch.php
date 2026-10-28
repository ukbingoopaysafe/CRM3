<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PhotosMatch: string implements HasColor, HasLabel
{
    case Matches = 'matches';
    case Partial = 'partial';
    case Mismatch = 'mismatch';

    public function getLabel(): string
    {
        return match ($this) {
            self::Matches => 'مطابقة',
            self::Partial => 'مطابقة جزئية',
            self::Mismatch => 'غير مطابقة',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Matches => 'success',
            self::Partial => 'warning',
            self::Mismatch => 'danger',
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
