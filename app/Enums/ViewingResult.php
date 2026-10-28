<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ViewingResult: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Interested = 'interested';
    case NotInterested = 'not_interested';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار النتيجة',
            self::Interested => 'مهتم',
            self::NotInterested => 'غير مهتم',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Interested => 'success',
            self::NotInterested => 'danger',
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
