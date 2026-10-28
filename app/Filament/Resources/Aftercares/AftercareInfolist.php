<?php

namespace App\Filament\Resources\Aftercares;

use App\Models\Base\Aftercare;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The relationship header. Everything that happens next lives in the
 * interactions relation below it (D-059).
 */
final class AftercareInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('سجل العلاقة')
                ->columns(4)
                ->components([
                    TextEntry::make('customer.name')->label('العميل'),
                    TextEntry::make('customer.phone')->label('الهاتف')->placeholder('—'),
                    TextEntry::make('owner.name')->label('المسؤول')->placeholder('—'),
                    TextEntry::make('next_follow_up_at')
                        ->label('المتابعة القادمة')
                        ->dateTime('Y-m-d H:i')
                        ->placeholder('بلا موعد')
                        ->color(fn (Aftercare $record): string => $record->next_follow_up_at?->isPast() ? 'danger' : 'gray'),
                    TextEntry::make('created_at')->label('بدأت في')->dateTime('Y-m-d H:i'),
                    TextEntry::make('activities_count')
                        ->label('التفاعلات')
                        ->state(fn (Aftercare $record): int => $record->activities()->count()),
                ]),
        ]);
    }
}
