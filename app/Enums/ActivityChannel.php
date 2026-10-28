<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ActivityChannel: string implements HasLabel
{
    case Call = 'call';
    case WhatsApp = 'whatsapp';
    case Meeting = 'meeting';
    case Email = 'email';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Call => 'مكالمة هاتفية',
            self::WhatsApp => 'WhatsApp',
            self::Meeting => 'مقابلة شخصية',
            self::Email => 'بريد إلكتروني',
            self::Other => 'أخرى',
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
