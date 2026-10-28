<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Currency: string implements HasLabel
{
    case EGP = 'EGP';
    case USD = 'USD';

    public function getLabel(): string
    {
        return match ($this) {
            self::EGP => 'جنيه مصري',
            self::USD => 'دولار أمريكي',
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
