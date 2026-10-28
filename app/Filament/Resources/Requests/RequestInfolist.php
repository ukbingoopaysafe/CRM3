<?php

namespace App\Filament\Resources\Requests;

use App\Enums\Currency;
use App\Enums\Department;
use App\Enums\Stage;
use App\Models\Base\Request;
use App\Support\RequestOptions;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only view of one request. Everything the employee needs to decide the
 * next move is on this page; nothing here is editable (editing is a separate
 * page so the lifecycle actions stay explicit).
 */
final class RequestInfolist
{
    public static function configure(Schema $schema, Department $department): Schema
    {
        $isRental = $department === Department::Rental;

        return $schema->components([
            Section::make('ملخص الطلب')
                ->columns(4)
                ->components([
                    TextEntry::make('id')->label('#'),
                    TextEntry::make('stage')
                        ->label('المرحلة')
                        ->badge()
                        ->formatStateUsing(fn (Stage $state, Request $record): string => $state->labelFor($record::department())),
                    TextEntry::make('status')->label('الحالة')->badge(),
                    TextEntry::make('outcome')->label('النتيجة')->badge()->placeholder('—'),
                    TextEntry::make('next_follow_up_at')
                        ->label('المتابعة القادمة')
                        ->dateTime('Y-m-d H:i')
                        ->placeholder('—'),
                    TextEntry::make('owner.name')->label('المسؤول')->placeholder('—'),
                    TextEntry::make('leadSource.name')->label('مصدر الوصول')->placeholder('—'),
                    TextEntry::make('created_at')->label('أُنشئ في')->dateTime('Y-m-d H:i'),
                ]),

            Section::make('الأطراف')
                ->columns(3)
                ->components([
                    TextEntry::make('customer.name')->label('العميل')->placeholder('غير معروف'),
                    TextEntry::make('customer.phone')->label('هاتف العميل')->placeholder('—'),
                    TextEntry::make('contact_party')->label('جهة الاتصال')->badge(),
                    TextEntry::make('broker.name')
                        ->label('الوسيط')
                        ->state(fn (Request $record): ?string => $record->broker?->name ?? $record->broker_name_snapshot)
                        ->placeholder('—'),
                    TextEntry::make('broker.phone')
                        ->label('هاتف الوسيط')
                        ->state(fn (Request $record): ?string => $record->broker?->phone ?? $record->broker_phone_snapshot)
                        ->placeholder('—'),
                ]),

            Section::make('الموقع المطلوب')
                ->columns(3)
                ->components([
                    TextEntry::make('governorate')->label('المحافظة')->badge()->color('gray'),
                    TextEntry::make('area.name')->label('المنطقة')->placeholder('—'),
                ]),

            Section::make('الاحتياج')
                ->columns(3)
                ->components($isRental ? self::rentalNeedEntries() : self::resaleNeedEntries()),

            Section::make('الميزانية')
                ->columns(3)
                ->components([
                    TextEntry::make('currency')
                        ->label('العملة')
                        ->formatStateUsing(fn (?Currency $state): string => $state?->getLabel() ?? '—'),
                    TextEntry::make('budget_declared')->label('الميزانية المعلنة')->numeric(decimalPlaces: 2)->placeholder('—'),
                    TextEntry::make('budget_target')->label('الميزانية المستهدفة')->numeric(decimalPlaces: 2)->placeholder('—'),
                ]),

            Section::make('التواريخ والملاحظات')
                ->columns(3)
                ->components([
                    TextEntry::make('paused_at')->label('أُوقف في')->dateTime('Y-m-d H:i')->placeholder('—'),
                    TextEntry::make('closed_at')->label('أُغلق في')->dateTime('Y-m-d H:i')->placeholder('—'),
                    TextEntry::make('outcome_reason_text')
                        ->label('سبب الإغلاق')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    TextEntry::make('notes')->label('ملاحظات عامة')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * @return array<int, TextEntry>
     */
    private static function rentalNeedEntries(): array
    {
        return [
            TextEntry::make('unit_type')
                ->label('نوع الوحدة')
                ->formatStateUsing(fn (?string $state): string => RequestOptions::UNIT_TYPES[$state] ?? '—'),
            TextEntry::make('rooms')->label('عدد الغرف')->placeholder('—'),
            TextEntry::make('occupants')->label('عدد الأفراد')->placeholder('—'),
            TextEntry::make('is_family')
                ->label('أسرة؟')
                ->formatStateUsing(fn (?string $state): string => RequestOptions::YES_NO[$state] ?? '—'),
            TextEntry::make('finishing')
                ->label('التشطيب')
                ->formatStateUsing(fn (?string $state): string => RequestOptions::FINISHING[$state] ?? '—'),
            TextEntry::make('floor_preference')
                ->label('تفضيل الدور')
                ->formatStateUsing(fn (?string $state): string => RequestOptions::FLOORS[$state] ?? '—'),
            TextEntry::make('elevator')
                ->label('المصعد')
                ->formatStateUsing(fn (?string $state): string => RequestOptions::ELEVATOR[$state] ?? '—'),
            TextEntry::make('rental_term')->label('مدة الإيجار')->placeholder('—'),
            TextEntry::make('rental_term_count')->label('عدد الفترات')->placeholder('—'),
            TextEntry::make('other_requirements')->label('متطلبات أخرى')->placeholder('—')->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, TextEntry>
     */
    private static function resaleNeedEntries(): array
    {
        return [
            TextEntry::make('unit_type')
                ->label('نوع الوحدة')
                ->formatStateUsing(fn (?string $state): string => RequestOptions::UNIT_TYPES[$state] ?? '—'),
            TextEntry::make('area_min')->label('أقل مساحة (م²)')->placeholder('—'),
            TextEntry::make('area_max')->label('أقصى مساحة (م²)')->placeholder('—'),
            TextEntry::make('other_requirements')->label('متطلبات أخرى')->placeholder('—')->columnSpanFull(),
        ];
    }
}
