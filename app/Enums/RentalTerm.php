<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Rental budget is a monthly instalment (D-046). Semi-annual and annual
 * contracts are paid monthly over the full term (D-048).
 */
enum RentalTerm: string implements HasLabel
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'يومي',
            self::Monthly => 'شهري',
            self::SemiAnnual => 'نصف سنوي',
            self::Annual => 'سنوي',
        };
    }

    public function paymentSchedule(): string
    {
        return $this === self::Daily ? 'daily' : 'monthly';
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
