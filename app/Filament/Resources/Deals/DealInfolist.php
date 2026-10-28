<?php

namespace App\Filament\Resources\Deals;

use App\Enums\CommissionMethod;
use App\Enums\Currency;
use App\Enums\Department;
use App\Enums\RentalTerm;
use App\Models\Base\Deal;
use App\Support\RequestOptions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only record of one deal. Nothing here is editable: the agreement is
 * written once and kept as it happened (D-058).
 */
final class DealInfolist
{
    public static function configure(Schema $schema, Department $department): Schema
    {
        $isRental = $department === Department::Rental;

        return $schema->components([
            Section::make('ملخص الصفقة')
                ->columns(4)
                ->components([
                    TextEntry::make('id')->label('#'),
                    TextEntry::make('deal_date')->label('تاريخ الصفقة')->date('Y-m-d'),
                    TextEntry::make('agreed_total_value')
                        ->label('القيمة الإجمالية')
                        ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, '.', ','))
                        ->weight('medium'),
                    TextEntry::make('currency')
                        ->label('العملة')
                        ->formatStateUsing(fn (?Currency $state): string => $state?->getLabel() ?? '—'),
                    TextEntry::make('request_id')->label('الطلب')->prefix('طلب #'),
                    TextEntry::make('creator.name')->label('سجّلها')->placeholder('—'),
                    TextEntry::make('created_at')->label('أُنشئت في')->dateTime('Y-m-d H:i'),
                ]),

            Section::make('الطرفان')
                ->description('العميل والوسيط مرآة لما كان على الطلب وقت الصفقة.')
                ->columns(4)
                ->components([
                    TextEntry::make('customer.name')->label('العميل')->placeholder('غير معروف'),
                    TextEntry::make('customer.phone')->label('هاتف العميل')->placeholder('—'),
                    TextEntry::make('broker.name')->label('الوسيط')->placeholder('—'),
                    TextEntry::make('broker.phone')->label('هاتف الوسيط')->placeholder('—'),
                ]),

            Section::make('الوحدة')
                ->description('وصف تاريخي اختياري، ولا ينشئ كتالوج وحدات.')
                ->columns(4)
                ->components([
                    TextEntry::make('unit_code')->label('الكود')->placeholder('—'),
                    TextEntry::make('unit_type')
                        ->label('النوع')
                        ->formatStateUsing(fn (?string $state): string => RequestOptions::UNIT_TYPES[$state] ?? '—'),
                    TextEntry::make('unit_area')
                        ->label('المساحة (م²)')
                        ->formatStateUsing(fn ($state): ?string => $state === null ? null : number_format((float) $state, 2, '.', ','))
                        ->placeholder('—'),
                    TextEntry::make('unit_location')->label('الموقع')->placeholder('—'),
                ]),

            $isRental ? self::rentalTermSection() : self::sellerSection(),

            Section::make('الأطراف والملاحظات')
                ->columns(3)
                ->components([
                    TextEntry::make('contact_representative')->label('جهة الاتصال')->placeholder('—'),
                    TextEntry::make('field_agent_name')->label('المندوب الميداني')->placeholder('—'),
                    TextEntry::make('notes')->label('ملاحظات')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('بنود العمولة')
                ->description('اتفاق موثق فقط: لا حالة سداد ولا تحصيل.')
                ->components([
                    RepeatableEntry::make('commissions')
                        ->hiddenLabel()
                        ->columns(4)
                        ->placeholder('لا توجد بنود عمولة مسجلة.')
                        ->schema([
                            TextEntry::make('payer')->label('الدافع'),
                            TextEntry::make('beneficiary')->label('المستفيد'),
                            TextEntry::make('method')
                                ->label('الطريقة')
                                ->badge()
                                ->formatStateUsing(fn (?CommissionMethod $state): string => $state?->getLabel() ?? '—'),
                            TextEntry::make('value')
                                ->label('القيمة')
                                ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, '.', ',')),
                            TextEntry::make('conditions')
                                ->label('الشروط')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    private static function rentalTermSection(): Section
    {
        return Section::make('العقد')
            ->columns(3)
            ->components([
                TextEntry::make('rental_term')
                    ->label('مدة الإيجار')
                    ->formatStateUsing(fn (?RentalTerm $state): string => $state?->getLabel() ?? '—'),
                TextEntry::make('rental_term_count')->label('عدد الفترات')->placeholder('—'),
                TextEntry::make('payment_schedule')
                    ->label('دورية السداد')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'daily' => 'يومي',
                        'monthly' => 'شهري',
                        default => '—',
                    }),
            ]);
    }

    private static function sellerSection(): Section
    {
        return Section::make('البائع')
            ->columns(2)
            ->components([
                TextEntry::make('seller_name')->label('اسم البائع')->placeholder('—'),
                TextEntry::make('seller_phone')->label('هاتف البائع')->placeholder('—'),
            ]);
    }
}
