<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The stage is the position inside the workflow. It is independent from the
 * request status and from the closing outcome (D-043).
 */
enum Stage: string implements HasColor, HasLabel
{
    case Received = 'received';
    case Qualification = 'qualification';
    case Searching = 'searching';
    case Recommending = 'recommending';
    case Viewing = 'viewing';
    case Negotiating = 'negotiating';

    public function getLabel(): string
    {
        return $this->labelFor(Department::Rental);
    }

    public function labelFor(Department $department): string
    {
        return match ($this) {
            self::Received => 'استقبال',
            self::Qualification => 'تأهيل الاحتياج',
            self::Searching => 'البحث عن وحدات',
            self::Recommending => $department === Department::Resale
                ? 'عرض/ترشيح وحدات'
                : 'ترشيح وحدات',
            self::Viewing => 'معاينة',
            self::Negotiating => 'تفاوض',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received => 'gray',
            self::Qualification => 'info',
            self::Searching => 'info',
            self::Recommending => 'warning',
            self::Viewing => 'warning',
            self::Negotiating => 'success',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(Department $department): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case) => [$case->value => $case->labelFor($department)]
        )->all();
    }
}
