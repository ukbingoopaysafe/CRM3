<?php

namespace App\Filament\Resources\Customers;

use App\Support\Countries;
use App\Support\Phone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Only the customer fields approved in D-030 are editable here (D-062).
 */
final class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات العميل')
                ->description('الاسم والهاتف مطلوبان. يُحفظ الهاتف بصيغة دولية موحّدة ويُمنع تكراره داخل هذا القسم فقط.')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label('الاسم')
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

                    TextInput::make('nationality')
                        ->label('الجنسية')
                        ->maxLength(60),
                ]),
        ]);
    }
}
