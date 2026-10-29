<?php

namespace App\Filament\Resources\Brokers;

use App\Filament\Concerns\ReportsDomainErrors;
use App\Models\Base\Broker;
use App\Models\User;
use App\Services\BrokerService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a broker is permanent and manager-only, so it always asks for a
 * written reason before calling the service (D-072).
 */
final class BrokersTable
{
    use ReportsDomainErrors;

    public static function configure(Table $table): Table
    {
        $self = new self;

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الوسيط')
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
                    ->url(fn (Broker $record): ?string => $self->whatsappUrl($record), shouldOpenInNewTab: true),

                TextColumn::make('requests_count')
                    ->label('الطلبات')
                    ->counts('requests')
                    ->alignCenter(),

                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('أُضيف في')
                    ->date('Y-m-d')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->label('تعديل')->tooltip('تعديل'),

                Action::make('delete')
                    ->label('حذف نهائي')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Broker $record): bool => $self->canDelete($record))
                    ->modalHeading('حذف سجل الوسيط نهائيًا')
                    ->modalDescription('يُحفظ اسم وهاتف الوسيط على الطلبات السابقة، لكن السجل نفسه لا يمكن استعادته. لا يمكن الحذف أثناء وجود طلب نشط.')
                    ->modalSubmitActionLabel('حذف نهائي')
                    ->schema([
                        Textarea::make('reason')
                            ->label('سبب الحذف')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('السبب يُسجَّل في سجل التدقيق ولا يظهر للعميل.'),
                    ])
                    ->action(function (Broker $record, array $data) use ($self): void {
                        $self->run(fn () => app(BrokerService::class)->delete($record, (string) $data['reason'], $self->actor()));
                    }),
            ])
            ->defaultSort('name')
            ->striped()
            ->emptyStateHeading('لا يوجد وسطاء في هذا القسم')
            ->emptyStateDescription('أضف أول وسيط لتبدأ ربط الطلبات به.')
            ->emptyStateIcon('heroicon-o-identification');
    }

    private function canDelete(Broker $record): bool
    {
        return $this->actor()?->can('delete', $record) ?? false;
    }

    private function actor(): ?User
    {
        return auth()->user();
    }

    private function whatsappUrl(Broker $record): ?string
    {
        return $record->whatsappUrl();
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
