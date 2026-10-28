<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Concerns\ReportsDomainErrors;
use App\Models\Base\Customer;
use App\Models\User;
use App\Services\AftercareService;
use App\Services\CustomerService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The list never mixes departments: each resource owns one model, and that
 * model owns one department (D-020). Archived rows stay visible on demand.
 */
final class CustomersTable
{
    use ReportsDomainErrors;

    public static function configure(Table $table): Table
    {
        $self = new self;

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->weight(FontWeight::Medium)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('تم نسخ الرقم')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->iconColor('success')
                    ->url(fn (Customer $record): ?string => $record->whatsappUrl(), shouldOpenInNewTab: true),

                TextColumn::make('nationality')
                    ->label('الجنسية')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('requests_count')
                    ->label('الطلبات')
                    ->counts('requests')
                    ->alignCenter(),

                TextColumn::make('archived_at')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'مؤرشف' : 'نشط')
                    ->color(fn ($state): string => $state ? 'warning' : 'success'),

                TextColumn::make('created_at')
                    ->label('أُضيف في')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('archived')
                    ->label('السجل')
                    ->options([
                        'active' => 'النشطون',
                        'archived' => 'المؤرشفون',
                    ])
                    ->default('active')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? 'active') {
                        'archived' => $query->whereNotNull('archived_at'),
                        'active' => $query->whereNull('archived_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('تعديل'),

                Action::make('archive')
                    ->label('أرشفة')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('أرشفة سجل العميل')
                    ->modalDescription('يبقى السجل والهاتف محجوزين ويُستبعد من الطلبات الجديدة. يمكن استعادته لاحقًا.')
                    ->visible(fn (Customer $record): bool => ! $record->isArchived() && $self->canArchive($record))
                    ->action(function (Customer $record) use ($self): void {
                        $self->run(fn () => app(CustomerService::class)->archive($record, $self->actor()));
                    }),

                Action::make('restore')
                    ->label('استعادة')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Customer $record): bool => $record->isArchived() && $self->canArchive($record))
                    ->action(function (Customer $record) use ($self): void {
                        $self->run(fn () => app(CustomerService::class)->restore($record, $self->actor()));
                    }),

                Action::make('startAftercare')
                    ->label('بدء ما بعد الصفقة')
                    ->icon('heroicon-o-heart')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('بدء سجل ما بعد الصفقة')
                    ->modalDescription('سجل واحد لكل عميل داخل القسم، ويُسند إليك. يبدأ فقط بعد صفقة ناجحة للعميل.')
                    ->visible(fn (Customer $record): bool => ! $record->aftercare()->exists()
                        && ($self->actor()?->can('startAftercare', $record) ?? false))
                    ->action(function (Customer $record) use ($self): void {
                        $self->run(
                            fn () => app(AftercareService::class)->ensureFor($record, $self->actor()),
                            'تم بدء سجل ما بعد الصفقة',
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('لا توجد سجلات عملاء بعد')
            ->emptyStateDescription('أنشئ أول سجل عميل في هذا القسم لتبدأ متابعة الطلبات.');
    }

    private function canArchive(Customer $record): bool
    {
        return $this->actor()?->can('archive', $record) ?? false;
    }

    private function actor(): ?User
    {
        return auth()->user();
    }

    private function run(\Closure $callback, ?string $successMessage = null): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->reportDomainError($exception);

            return;
        }

        if ($successMessage !== null) {
            Notification::make()->success()->title($successMessage)->send();
        }
    }
}
