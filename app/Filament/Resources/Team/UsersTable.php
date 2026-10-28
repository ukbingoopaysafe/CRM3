<?php

namespace App\Filament\Resources\Team;

use App\Enums\Department;
use App\Enums\Role;
use App\Filament\Concerns\ReportsDomainErrors;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Managers and employees live in one list, because the manager is the person
 * who creates both (D-080). Accounts are deactivated, never deleted, so past
 * work keeps its author (D-071).
 */
final class UsersTable
{
    use ReportsDomainErrors;

    public static function configure(Table $table): Table
    {
        $self = new self;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('memberships'))
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->description(fn (User $record): ?string => $record->job_title)
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->label('الدور')
                    ->badge()
                    ->color(fn (Role $state): string => $state === Role::Manager ? 'success' : 'gray')
                    ->sortable(),

                TextColumn::make('departments')
                    ->label('الأقسام')
                    ->state(fn (User $record): string => $record->isManager()
                        ? 'القسمان'
                        : $record->memberships
                            ->map(fn ($membership): string => $membership->department->getLabel())
                            ->join('، ')),

                TextColumn::make('is_active')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'موقوف')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),

                TextColumn::make('last_login_at')
                    ->label('آخر دخول')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('لم يدخل بعد')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('الدور')
                    ->options(Role::options()),

                SelectFilter::make('department')
                    ->label('القسم')
                    ->options(Department::options())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['value'] ?? null,
                            fn (Builder $query, string $department): Builder => $query
                                ->whereHas('memberships', fn (Builder $memberships): Builder => $memberships
                                    ->where('department', $department)),
                        )),

                SelectFilter::make('is_active')
                    ->label('الحالة')
                    ->options([
                        '1' => 'نشط',
                        '0' => 'موقوف',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            filled($data['value'] ?? null),
                            fn (Builder $query) => $query->where('is_active', (bool) $data['value']),
                        )),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('تعديل')
                    ->visible(fn (User $record): bool => auth()->user()?->can('update', $record) ?? false),

                Action::make('deactivate')
                    ->label('تعطيل الحساب')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('تعطيل الحساب')
                    ->modalDescription('يفقد صاحب الحساب الوصول إلى اللوحة فورًا، وتبقى أعماله المسجلة كما هي.')
                    ->visible(fn (User $record): bool => $record->is_active
                        && (auth()->user()?->can('deactivate', $record) ?? false))
                    ->action(function (User $record) use ($self): void {
                        $self->run(fn () => app(UserAccountService::class)->setActive($record, false, auth()->user()));
                    }),

                Action::make('activate')
                    ->label('تفعيل الحساب')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! $record->is_active
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (User $record) use ($self): void {
                        $self->run(fn () => app(UserAccountService::class)->setActive($record, true, auth()->user()));
                    }),
            ])
            ->defaultSort('name')
            ->striped()
            ->emptyStateHeading('لا يوجد أعضاء بعد')
            ->emptyStateDescription('أنشئ حساب مدير شركة أو موظف ليبدأ العمل.');
    }

    private function run(\Closure $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->reportDomainError($exception);
        }
    }
}
