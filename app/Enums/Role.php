<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Company manager always has both departments (D-063); employees are
 * assigned explicitly through the department_user membership table (D-034).
 */
enum Role: string implements HasLabel
{
    case Manager = 'manager';
    case Employee = 'employee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manager => 'مدير الشركة',
            self::Employee => 'موظف',
        };
    }

    public function isManager(): bool
    {
        return $this === self::Manager;
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
