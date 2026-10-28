<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Two operationally isolated departments (D-020).
 * A record created inside one department is never visible from the other.
 */
enum Department: string implements HasLabel
{
    case Rental = 'rental';
    case Resale = 'resale';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rental => 'الإيجار',
            self::Resale => 'إعادة البيع',
        };
    }

    public function slug(): string
    {
        return $this->value;
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
