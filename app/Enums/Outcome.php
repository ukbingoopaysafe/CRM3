<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Outcome: string implements HasColor, HasLabel
{
    case Successful = 'successful';
    case Unsuccessful = 'unsuccessful';

    public function getLabel(): string
    {
        return match ($this) {
            self::Successful => 'ناجح',
            self::Unsuccessful => 'غير ناجح',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Successful => 'success',
            self::Unsuccessful => 'danger',
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
