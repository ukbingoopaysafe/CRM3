<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactParty: string implements HasLabel
{
    case Customer = 'customer';
    case Broker = 'broker';

    public function getLabel(): string
    {
        return match ($this) {
            self::Customer => 'العميل',
            self::Broker => 'الوسيط',
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
