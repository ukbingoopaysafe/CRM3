<?php

namespace App\Filament\Resources\Requests;

use App\Enums\ContactParty;
use App\Enums\Currency;
use App\Enums\Department;
use App\Enums\Governorate;
use App\Enums\RentalTerm;
use App\Models\Area;
use App\Models\Base\Customer;
use App\Models\Rental;
use App\Models\Resale;
use App\Models\User;
use App\Support\RequestOptions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Only the approved request fields live here (D-048, WORKFLOWS §2.7). The
 * owner is chosen once, at creation; later it moves through a reassignment
 * decision only (D-070).
 */
final class RequestForm
{
    public static function configure(Schema $schema, Department $department): Schema
    {
        $isRental = $department === Department::Rental;

        return $schema->components([
            Section::make('أطراف الطلب')
                ->description('يلزم عميل معروف أو وسيط على الأقل. عند وجود الاثنين يحدد الموظف جهة الاتصال الفعلية.')
                ->columns(2)
                ->components([
                    Select::make('customer_id')
                        ->label('العميل')
                        ->options(fn (): array => self::customerOptions($department))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->placeholder('غير معروف بعد'),

                    Select::make('broker_id')
                        ->label('الوسيط')
                        ->options(fn (): array => self::brokerOptions($department))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->placeholder('لا يوجد وسيط'),

                    Select::make('contact_party')
                        ->label('جهة الاتصال الفعلية')
                        ->options(ContactParty::options())
                        ->required(fn (Get $get): bool => filled($get('customer_id')) && filled($get('broker_id')))
                        ->visible(fn (Get $get): bool => filled($get('customer_id')) && filled($get('broker_id')))
                        ->helperText('الطرف الذي يتحدث معه الموظف فعليًا. يُحدد تلقائيًا عند وجود طرف واحد.'),
                ]),

            Section::make('المسؤول ومصدر الوصول')
                ->columns(2)
                ->components([
                    Select::make('owner_user_id')
                        ->label('مسؤول الطلب')
                        ->options(fn (): array => self::ownerOptions($department))
                        ->default(fn (): ?int => auth()->id())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn (?object $record): bool => $record !== null)
                        ->dehydrated()
                        ->helperText('تغيير المسؤول بعد الإنشاء يمر عبر طلب إعادة تعيين معتمد.'),

                    Select::make('lead_source_id')
                        ->label('مصدر الوصول')
                        ->options(fn (): array => self::leadSourceOptions($department))
                        ->searchable()
                        ->live()
                        ->placeholder('غير محدد'),

                    TextInput::make('lead_source_other')
                        ->label('وصف المصدر')
                        ->maxLength(120)
                        ->required(fn (Get $get): bool => self::leadSourceNeedsDescription($get('lead_source_id')))
                        ->visible(fn (Get $get): bool => self::leadSourceNeedsDescription($get('lead_source_id'))),
                ]),

            Section::make('الموقع المطلوب')
                ->columns(2)
                ->components([
                    Select::make('governorate')
                        ->label('المحافظة')
                        ->options(Governorate::options())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('area_id', null)),

                    Select::make('area_id')
                        ->label('المنطقة')
                        ->options(fn (Get $get): array => Area::query()
                            ->selectable()
                            ->forGovernorate($get('governorate'))
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Area $area) => [$area->getKey() => $area->display_name])
                            ->all())
                        ->searchable()
                        ->required()
                        ->helperText('القائمة مرتبطة بالمحافظة المختارة. تُدار المناطق من شاشة المناطق.'),
                ]),

            Section::make('الاحتياج')
                ->columns(3)
                ->components($isRental ? self::rentalNeedFields() : self::resaleNeedFields()),

            Section::make('الميزانية')
                ->columns(3)
                ->components([
                    Select::make('currency')
                        ->label('العملة')
                        ->options(Currency::options())
                        ->default(Currency::EGP->value),

                    TextInput::make('budget_declared')
                        ->label('الميزانية المعلنة')
                        ->numeric()
                        ->minValue(0)
                        ->helperText($isRental ? 'قسط شهري.' : 'إجمالي، وليس للمتر.'),

                    TextInput::make('budget_target')
                        ->label('الميزانية المستهدفة')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('تُحدّث بعد التأهيل دون طمس القيمة المعلنة.'),
                ]),

            Section::make('المتابعة والملاحظات')
                ->columns(2)
                ->components([
                    DateTimePicker::make('next_follow_up_at')
                        ->label('موعد المتابعة القادم')
                        ->required()
                        ->seconds(false)
                        ->minutesStep(15)
                        ->helperText('إلزامي للطلب النشط؛ الإيقاف المؤقت يعفيه حتى الاستئناف.'),

                    Textarea::make('notes')
                        ->label('ملاحظات عامة')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function rentalNeedFields(): array
    {
        return [
            Select::make('unit_type')
                ->label('نوع الوحدة')
                ->options(RequestOptions::unitTypes(Department::Rental))
                ->placeholder('غير محدد'),

            TextInput::make('rooms')
                ->label('عدد الغرف')
                ->numeric()
                ->minValue(1)
                ->maxValue(20),

            TextInput::make('occupants')
                ->label('عدد الأفراد')
                ->numeric()
                ->minValue(1)
                ->maxValue(30),

            ToggleButtons::make('is_family')
                ->label('أسرة؟')
                ->options(RequestOptions::YES_NO)
                ->inline(),

            Select::make('finishing')
                ->label('التشطيب')
                ->options(RequestOptions::FINISHING)
                ->placeholder('غير محدد'),

            Select::make('floor_preference')
                ->label('تفضيل الدور')
                ->options(RequestOptions::FLOORS)
                ->placeholder('غير محدد'),

            Select::make('elevator')
                ->label('المصعد')
                ->options(RequestOptions::ELEVATOR)
                ->placeholder('غير محدد'),

            Select::make('rental_term')
                ->label('مدة الإيجار')
                ->options(RentalTerm::options())
                ->placeholder('غير محدد'),

            TextInput::make('rental_term_count')
                ->label('عدد فترات المدة')
                ->numeric()
                ->minValue(1)
                ->visible(fn (Get $get): bool => filled($get('rental_term'))),

            Textarea::make('other_requirements')
                ->label('متطلبات أخرى')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function resaleNeedFields(): array
    {
        return [
            Select::make('unit_type')
                ->label('نوع الوحدة')
                ->options(RequestOptions::unitTypes(Department::Resale))
                ->placeholder('غير محدد'),

            TextInput::make('area_min')
                ->label('أقل مساحة (م²)')
                ->numeric()
                ->minValue(0),

            TextInput::make('area_max')
                ->label('أقصى مساحة (م²)')
                ->numeric()
                ->minValue(0),

            Textarea::make('other_requirements')
                ->label('متطلبات أخرى')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function customerOptions(Department $department): array
    {
        return self::customerQuery($department)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Customer $customer) => [
                $customer->getKey() => $customer->name.' — '.$customer->phone,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function brokerOptions(Department $department): array
    {
        $model = $department === Department::Rental ? Rental\Broker::class : Resale\Broker::class;

        return $model::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($broker) => [$broker->getKey() => $broker->name.' — '.$broker->phone])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function ownerOptions(Department $department): array
    {
        return User::query()
            ->inDepartment($department)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function leadSourceOptions(Department $department): array
    {
        $model = $department === Department::Rental ? Rental\LeadSource::class : Resale\LeadSource::class;

        return $model::query()->active()->orderBy('sort_order')->pluck('name', 'id')->all();
    }

    private static function leadSourceNeedsDescription(mixed $leadSourceId): bool
    {
        if (blank($leadSourceId)) {
            return false;
        }

        $model = Department::Rental
            ? Rental\LeadSource::class
            : Resale\LeadSource::class;

        return (bool) $model::query()->whereKey($leadSourceId)->value('requires_description');
    }

    /**
     * @return Builder<Customer>
     */
    private static function customerQuery(Department $department): Builder
    {
        $model = $department === Department::Rental ? Rental\Customer::class : Resale\Customer::class;

        return $model::query()->whereNull('archived_at');
    }
}
