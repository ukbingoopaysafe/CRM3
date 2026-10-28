<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RecommendationStatus: string implements HasColor, HasLabel
{
    case Suggested = 'suggested';
    case Sent = 'sent';
    case Selected = 'selected';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Suggested => 'مقترحة',
            self::Sent => 'أرسلت للعميل',
            self::Selected => 'اختارها العميل',
            self::Rejected => 'رفضها العميل',
            self::Withdrawn => 'سُحبت',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Suggested => 'gray',
            self::Sent => 'info',
            self::Selected => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'warning',
        };
    }

    /**
     * A field inspection is only allowed after the unit was sent or selected (D-060).
     */
    public function allowsInspection(): bool
    {
        return in_array($this, [self::Sent, self::Selected], true);
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
