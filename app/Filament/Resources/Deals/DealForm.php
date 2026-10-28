<?php

namespace App\Filament\Resources\Deals;

use App\Enums\CommissionMethod;
use App\Enums\Currency;
use App\Enums\Department;
use App\Enums\Outcome;
use App\Enums\RentalTerm;
use App\Enums\RequestStatus;
use App\Models\Base\Request;
use App\Models\Rental;
use App\Models\Resale;
use App\Support\RequestOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The approved deal fields (D-058). The customer and the broker are inherited
 * from the request, and commission lines only record the agreement: there is
 * no rate ceiling and no payment status.
 */
final class DealForm
{
    public static function configure(Schema $schema, Department $department): Schema
    {
        $isRental = $department === Department::Rental;

        return $schema->components([
            Section::make('الطلب والطرفان')
                ->description('تُسجل الصفقة مرة واحدة، من طلب مغلق نتيجته ناجحة. العميل والوسيط يرثان من الطلب.')
                ->components([
                    Select::make('request_id')
                        ->label('الطلب')
                        ->options(fn (): array => self::eligibleRequests($department))
                        ->searchable()
                        ->required()
                        ->default(fn (): ?int => request()->integer('request_id') ?: null)
                        ->helperText('لا تظهر إلا الطلبات الناجحة التي لم تُسجل لها صفقة.'),
                ]),

            Section::make('قيم الصفقة')
                ->columns(3)
                ->components([
                    DatePicker::make('deal_date')
                        ->label('تاريخ الصفقة')
                        ->required()
                        ->default(fn () => now())
                        ->maxDate(fn () => now()),

                    TextInput::make('agreed_total_value')
                        ->label('القيمة الإجمالية')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->step(0.01),

                    Select::make('currency')
                        ->label('العملة')
                        ->options(Currency::options())
                        ->default(Currency::EGP->value)
                        ->required(),
                ]),

            Section::make('بيانات الوحدة')
                ->description('اختيارية، وتبقى وصفًا تاريخيًا للصفقة. لا يُنشئ النظام كتالوج وحدات.')
                ->columns(2)
                ->components([
                    TextInput::make('unit_code')
                        ->label('كود الوحدة')
                        ->maxLength(32),

                    Select::make('unit_type')
                        ->label('نوع الوحدة')
                        ->options(RequestOptions::unitTypes($department))
                        ->placeholder('غير محدد'),

                    TextInput::make('unit_area')
                        ->label('المساحة (م²)')
                        ->numeric()
                        ->minValue(0),

                    TextInput::make('unit_location')
                        ->label('الموقع')
                        ->maxLength(255),
                ]),

            $isRental ? self::rentalTermSection() : self::sellerSection(),

            Section::make('الأطراف والملاحظات')
                ->description('أسماء نصية تاريخية بلا حساب دخول.')
                ->columns(2)
                ->components([
                    TextInput::make('contact_representative')
                        ->label('جهة الاتصال')
                        ->maxLength(255),

                    TextInput::make('field_agent_name')
                        ->label('المندوب الميداني')
                        ->maxLength(255),

                    Textarea::make('notes')
                        ->label('ملاحظات')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),

            Section::make('بنود العمولة')
                ->description('كل بند له دافعه ومستفيده وطريقته وقيمته. الشروط اختيارية، ولا حالة سداد.')
                ->components([
                    Repeater::make('commissions')
                        ->label('بنود الاتفاق')
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('إضافة بند عمولة')
                        ->itemLabel(fn (array $state): ?string => filled($state['beneficiary'] ?? null)
                            ? (string) $state['beneficiary']
                            : null)
                        ->schema([
                            TextInput::make('payer')
                                ->label('الدافع')
                                ->maxLength(255)
                                ->required(),

                            TextInput::make('beneficiary')
                                ->label('المستفيد')
                                ->maxLength(255)
                                ->required(),

                            Select::make('method')
                                ->label('الطريقة')
                                ->options(CommissionMethod::options())
                                ->default(CommissionMethod::Fixed->value)
                                ->required(),

                            TextInput::make('value')
                                ->label('القيمة')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->helperText('قيمة ثابتة أو نسبة مئوية، دون حد أعلى معتمد.'),

                            Textarea::make('conditions')
                                ->label('الشروط')
                                ->rows(2)
                                ->maxLength(1000)
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    private static function rentalTermSection(): Section
    {
        return Section::make('العقد')
            ->columns(2)
            ->components([
                Select::make('rental_term')
                    ->label('مدة الإيجار')
                    ->options(RentalTerm::options())
                    ->placeholder('غير محدد'),

                TextInput::make('rental_term_count')
                    ->label('عدد فترات المدة')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn (callable $get): bool => filled($get('rental_term')))
                    ->helperText('الإيجار اليومي يُسدد يوميًا، وما عداه شهريًا على كامل المدة.'),
            ]);
    }

    private static function sellerSection(): Section
    {
        return Section::make('البائع')
            ->description('يُسجل داخل صفقة إعادة البيع فقط، ولا يُنشئ طلب بائع مستقلًا.')
            ->columns(2)
            ->components([
                TextInput::make('seller_name')
                    ->label('اسم البائع')
                    ->maxLength(255),

                TextInput::make('seller_phone')
                    ->label('هاتف البائع')
                    ->tel()
                    ->maxLength(32),
            ]);
    }

    /**
     * Only successfully closed requests that still have no deal. The list is
     * capped because the select loads its options eagerly.
     *
     * @return array<int, string>
     */
    private static function eligibleRequests(Department $department): array
    {
        /** @var class-string<Request> $model */
        $model = $department === Department::Rental ? Rental\Request::class : Resale\Request::class;

        return $model::query()
            ->where('status', RequestStatus::Closed->value)
            ->where('outcome', Outcome::Successful->value)
            ->whereDoesntHave('deal')
            ->with(['customer', 'broker'])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (Request $request) => [
                $request->getKey() => 'طلب #'.$request->getKey().' — '.($request->contactName() ?? 'بلا طرف'),
            ])
            ->all();
    }
}
