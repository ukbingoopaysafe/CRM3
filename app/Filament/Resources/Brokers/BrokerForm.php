<?php

namespace App\Filament\Resources\Brokers;

use App\Support\Countries;
use App\Support\Phone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Brokers are directory records: a name and one phone number that must stay
 * unique inside the department (D-026).
 */
final class BrokerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الوسيط')
                ->description('الوسيط سجل مستقل داخل هذا القسم. لا يُطابق بين الأقسام ولا يُشارك بينها.')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label('اسم الوسيط')
                        ->required()
                        ->maxLength(120)
                        ->autofocus(),

                    TextInput::make('phone')
                        ->label('رقم الهاتف')
                        ->tel()
                        ->required()
                        ->maxLength(32)
                        ->helperText('مثال: 01012345678 أو ‎+201012345678')
                        ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            if (! Phone::isValid((string) $value, $get('phone_country'))) {
                                $fail('رقم الهاتف غير صالح. تحقق من الرقم أو من دولة الرقم.');
                            }
                        }),

                    Select::make('phone_country')
                        ->label('دولة الرقم')
                        ->options(Countries::options())
                        ->default(Phone::DEFAULT_COUNTRY)
                        ->searchable()
                        ->selectablePlaceholder(false),
                ]),

            Section::make('ملاحظات')
                ->collapsed()
                ->components([
                    Textarea::make('notes')
                        ->label('ملاحظات داخلية')
                        ->rows(3)
                        ->maxLength(1000)
                        ->helperText('لا تظهر للعميل. تُحفظ مع سجل الوسيط فقط.'),
                ]),
        ]);
    }
}
