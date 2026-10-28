<?php

namespace App\Filament\Resources\Aftercares\Concerns;

use App\Enums\ActivityChannel;
use App\Enums\Department;
use App\Filament\Concerns\ReportsDomainErrors;
use App\Models\Base\Aftercare;
use App\Models\Rental;
use App\Models\Resale;
use App\Services\AftercareService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Recording one interaction: immutable history, a mandatory result, and an
 * optional follow-up date that replaces the previous one, or clears it when
 * left empty (D-059).
 */
trait HasAftercareActions
{
    use ReportsDomainErrors;

    protected function recordActivityAction(): Action
    {
        return Action::make('recordAftercareActivity')
            ->label('تسجيل تفاعل')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('primary')
            ->visible(fn (Aftercare $record): bool => auth()->user()?->can('recordActivity', $record) ?? false)
            ->modalHeading('تسجيل تفاعل ما بعد الصفقة')
            ->modalDescription('التفاعل سجل تاريخي لا يُعدّل. الموعد الذي تكتبه هنا يستبدل المتابعة الحالية، وتركه فارغًا يمسحها.')
            ->schema(fn (Aftercare $record): array => [
                Select::make('channel')
                    ->label('وسيلة التواصل')
                    ->options(ActivityChannel::options())
                    ->required()
                    ->default(ActivityChannel::Call->value),

                DateTimePicker::make('occurred_at')
                    ->label('وقت التفاعل')
                    ->required()
                    ->seconds(false)
                    ->minutesStep(5)
                    ->maxDate(fn (): Carbon => now())
                    ->default(fn (): Carbon => now()),

                Textarea::make('result')
                    ->label('النتيجة')
                    ->rows(3)
                    ->required(),

                DateTimePicker::make('next_follow_up_at')
                    ->label('المتابعة القادمة (اختيارية)')
                    ->seconds(false)
                    ->minutesStep(15)
                    ->helperText('اتركه فارغًا لمسح الموعد الحالي.'),

                Select::make('deal_id')
                    ->label('الصفقة المرتبطة (اختيارية)')
                    ->options(fn (): array => $this->dealOptions($record))
                    ->searchable()
                    ->placeholder('بلا صفقة'),

                Textarea::make('referral_details')
                    ->label('إحالة أو فرصة مستقبلية')
                    ->rows(2),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(2),
            ])
            ->action(function (Aftercare $record, array $data): void {
                $this->runDomainAction(function () use ($record, $data): void {
                    app(AftercareService::class)->recordActivity($record, [
                        'channel' => $data['channel'],
                        'occurred_at' => Carbon::parse($data['occurred_at']),
                        'result' => $data['result'],
                        'notes' => $data['notes'] ?? null,
                        'referral_details' => $data['referral_details'] ?? null,
                        'deal_id' => filled($data['deal_id'] ?? null) ? (int) $data['deal_id'] : null,
                        'next_follow_up_at' => filled($data['next_follow_up_at'] ?? null)
                            ? Carbon::parse($data['next_follow_up_at'])
                            : null,
                    ], auth()->user());
                }, 'تم تسجيل التفاعل');
            });
    }

    /**
     * Only deals of the same customer: the service rejects anything else.
     *
     * @return array<int, string>
     */
    private function dealOptions(Aftercare $record): array
    {
        $model = $record::department() === Department::Rental ? Rental\Deal::class : Resale\Deal::class;

        return $model::query()
            ->where('customer_id', $record->customer_id)
            ->orderByDesc('deal_date')
            ->get()
            ->mapWithKeys(fn ($deal): array => [
                $deal->getKey() => 'صفقة #'.$deal->getKey().' — '.number_format((float) $deal->agreed_total_value, 2).' '.$deal->currency->value,
            ])
            ->all();
    }

    private function runDomainAction(\Closure $callback, string $successMessage): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->reportDomainError($exception);

            return;
        }

        Notification::make()->success()->title($successMessage)->send();
    }
}
