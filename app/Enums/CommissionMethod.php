<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Commission terms record the agreement only; there is no default percentage
 * and no collection or payment status tracking (D-037, D-058).
 */
enum CommissionMethod: string implements HasLabel
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'قيمة ثابتة',
            self::Percentage => 'نسبة',
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
