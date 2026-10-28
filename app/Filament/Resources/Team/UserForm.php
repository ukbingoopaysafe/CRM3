<?php

namespace App\Filament\Resources\Team;

use App\Enums\Department;
use App\Enums\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The form never decides the rules by itself: it only mirrors what
 * UserAccountService enforces, so the same account cannot be created with
 * different rules from the console (D-034, D-080).
 */
final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الحساب')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(120)
                        ->autofocus(),

                    TextInput::make('email')
                        ->label('البريد الإلكتروني')
                        ->email()
                        ->required()
                        ->maxLength(120)
                        ->unique(ignoreRecord: true),

                    TextInput::make('job_title')
                        ->label('المسمى الوظيفي')
                        ->maxLength(120),

                    TextInput::make('phone')
                        ->label('رقم الهاتف')
                        ->tel()
                        ->maxLength(30),
                ]),

            Section::make('الدور والأقسام')
                ->description('مدير الشركة يرى القسمين دائمًا؛ الموظف يرى الأقسام المحددة له فقط.')
                ->columns(2)
                ->components([
                    Select::make('role')
                        ->label('الدور')
                        ->options(Role::options())
                        ->default(Role::Employee->value)
                        ->required()
                        ->live()
                        ->selectablePlaceholder(false),

                    CheckboxList::make('departments')
                        ->label('الأقسام')
                        ->options(Department::options())
                        ->required()
                        ->visible(fn (Get $get): bool => $get('role') === Role::Employee->value)
                        ->columns(2),

                    Toggle::make('can_manage_areas')
                        ->label('السماح بإدارة المناطق')
                        ->helperText('صلاحية ضيقة تسمح للموظف بإضافة مناطق الإيجار فقط.')
                        ->visible(fn (Get $get): bool => $get('role') === Role::Employee->value),
                ]),

            Section::make('كلمة المرور')
                ->description('تُترك فارغة عند التعديل للإبقاء على كلمة المرور الحالية.')
                ->columns(2)
                ->components([
                    TextInput::make('password')
                        ->label('كلمة المرور')
                        ->password()
                        ->revealable()
                        ->minLength(12)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state)),

                    TextInput::make('password_confirmation')
                        ->label('تأكيد كلمة المرور')
                        ->password()
                        ->revealable()
                        ->same('password')
                        ->dehydrated(false)
                        ->required(fn (string $operation): bool => $operation === 'create'),
                ]),
        ]);
    }
}
