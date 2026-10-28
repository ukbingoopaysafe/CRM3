<?php

namespace App\Filament\Resources\Requests\Concerns;

use App\Enums\ActivityChannel;
use App\Enums\Department;
use App\Enums\Outcome;
use App\Enums\RequestStatus;
use App\Enums\Stage;
use App\Filament\Concerns\ReportsDomainErrors;
use App\Models\Base\Request;
use App\Models\Rental\CloseReason;
use App\Services\ActivityService;
use App\Services\RequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The four lifecycle moves approved in D-043/D-050: stage, pause, resume and
 * close. Every one of them goes through RequestService so the history and the
 * audit trail are written the same way for the panel and the tests.
 */
trait HasRequestLifecycleActions
{
    use ReportsDomainErrors;

    protected function stageAction(): Action
    {
        return Action::make('changeStage')
            ->label('تغيير المرحلة')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->visible(fn (Request $record): bool => ! $record->isClosed())
            ->modalHeading('نقل الطلب إلى مرحلة أخرى')
            ->schema(fn (Request $record): array => [
                Select::make('stage')
                    ->label('المرحلة الجديدة')
                    ->options(Stage::options($record::department()))
                    ->default($record->stage->value)
                    ->required(),

                Textarea::make('note')
                    ->label('ملاحظة (اختيارية)')
                    ->rows(2),
            ])
            ->action(function (Request $record, array $data): void {
                $this->runDomainAction(function () use ($record, $data): void {
                    app(RequestService::class)->changeStage(
                        $record,
                        Stage::from($data['stage']),
                        auth()->user(),
                        filled($data['note'] ?? null) ? (string) $data['note'] : null,
                    );
                }, 'تم تحديث مرحلة الطلب');
            });
    }

    protected function pauseAction(): Action
    {
        return Action::make('pause')
            ->label('إيقاف مؤقت')
            ->icon('heroicon-o-pause-circle')
            ->color('warning')
            ->visible(fn (Request $record): bool => $record->isActive())
            ->requiresConfirmation()
            ->modalHeading('إيقاف الطلب مؤقتًا')
            ->modalDescription('تحتفظ المرحلة الحالية كما هي، ويُعفى الطلب من موعد المتابعة حتى الاستئناف.')
            ->schema([
                Textarea::make('note')
                    ->label('سبب الإيقاف (اختياري)')
                    ->rows(2),
            ])
            ->action(function (Request $record, array $data): void {
                $this->runDomainAction(function () use ($record, $data): void {
                    app(RequestService::class)->pause(
                        $record,
                        auth()->user(),
                        filled($data['note'] ?? null) ? (string) $data['note'] : null,
                    );
                }, 'تم إيقاف الطلب مؤقتًا');
            });
    }

    protected function resumeAction(): Action
    {
        return Action::make('resume')
            ->label('استئناف')
            ->icon('heroicon-o-play-circle')
            ->color('success')
            ->visible(fn (Request $record): bool => $record->status === RequestStatus::Paused)
            ->modalHeading('استئناف الطلب')
            ->modalDescription('يعود الطلب نشطًا في نفس المرحلة، ويلزم تحديد موعد المتابعة القادم.')
            ->schema([
                DateTimePicker::make('next_follow_up_at')
                    ->label('موعد المتابعة القادم')
                    ->required()
                    ->seconds(false)
                    ->minutesStep(15)
                    ->default(fn (): Carbon => now()->addDay()),
            ])
            ->action(function (Request $record, array $data): void {
                $this->runDomainAction(function () use ($record, $data): void {
                    app(RequestService::class)->resume(
                        $record,
                        Carbon::parse($data['next_follow_up_at']),
                        auth()->user(),
                    );
                }, 'تم استئناف الطلب');
            });
    }

    protected function closeAction(): Action
    {
        return Action::make('close')
            ->label('إغلاق الطلب')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->visible(fn (Request $record): bool => ! $record->isClosed())
            ->modalHeading('إغلاق الطلب')
            ->modalDescription('الإغلاق لا ينشئ صفقة تلقائيًا. الإغلاق غير الناجح يتطلب سببًا بصياغة الموظف.')
            ->modalSubmitActionLabel('إغلاق الطلب')
            ->schema(fn (Request $record): array => [
                Select::make('outcome')
                    ->label('نتيجة الطلب')
                    ->options(Outcome::options())
                    ->required()
                    ->live(),

                Select::make('outcome_reason_id')
                    ->label('سبب الإغلاق من القائمة')
                    ->options(fn (): array => $this->closeReasonOptions($record))
                    ->searchable()
                    ->placeholder('غير محدد'),

                Textarea::make('outcome_reason_text')
                    ->label('سبب الإغلاق بصياغة الموظف')
                    ->rows(3)
                    ->required(fn (Get $get): bool => $get('outcome') === Outcome::Unsuccessful->value)
                    ->visible(fn (Get $get): bool => $get('outcome') === Outcome::Unsuccessful->value)
                    ->helperText('يبقى على الطلب ولا يُنسخ إلى سجل التدقيق.'),

                Textarea::make('outcome_notes')
                    ->label('ملاحظات الإغلاق')
                    ->rows(2),
            ])
            ->action(function (Request $record, array $data): void {
                $this->runDomainAction(function () use ($record, $data): void {
                    app(RequestService::class)->close(
                        $record,
                        Outcome::from($data['outcome']),
                        auth()->user(),
                        filled($data['outcome_reason_text'] ?? null) ? (string) $data['outcome_reason_text'] : null,
                        filled($data['outcome_reason_id'] ?? null) ? (int) $data['outcome_reason_id'] : null,
                        filled($data['outcome_notes'] ?? null) ? (string) $data['outcome_notes'] : null,
                    );
                }, 'تم إغلاق الطلب');
            });
    }

    protected function activityAction(): Action
    {
        return Action::make('logActivity')
            ->label('تسجيل تواصل')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('primary')
            ->visible(fn (Request $record): bool => $record->acceptsActivities())
            ->modalHeading('تسجيل تواصل مكتمل')
            ->modalDescription('يُثبت النشاط بجهة الاتصال الحالية. التالي المطلوب لا يغيّر موعد المتابعة تلقائيًا.')
            ->schema([
                Select::make('channel')
                    ->label('وسيلة التواصل')
                    ->options(ActivityChannel::options())
                    ->required()
                    ->default(ActivityChannel::Call->value),

                DateTimePicker::make('occurred_at')
                    ->label('وقت التواصل')
                    ->required()
                    ->seconds(false)
                    ->minutesStep(5)
                    ->maxDate(fn (): Carbon => now())
                    ->default(fn (): Carbon => now()),

                Textarea::make('result')
                    ->label('نتيجة التواصل')
                    ->rows(3)
                    ->required(),

                Textarea::make('next_action')
                    ->label('الخطوة التالية')
                    ->rows(2),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(2),
            ])
            ->action(function (Request $record, array $data): void {
                $this->runDomainAction(function () use ($record, $data): void {
                    app(ActivityService::class)->record(
                        $record,
                        [
                            'channel' => $data['channel'],
                            'occurred_at' => Carbon::parse($data['occurred_at']),
                            'result' => $data['result'],
                            'next_action' => $data['next_action'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ],
                        auth()->user(),
                    );
                }, 'تم تسجيل التواصل');
            });
    }

    /**
     * Active close reasons of the same department as the record (D-050).
     *
     * @return array<int, string>
     */
    private function closeReasonOptions(Request $record): array
    {
        $model = $record::department() === Department::Rental
            ? CloseReason::class
            : \App\Models\Resale\CloseReason::class;

        return $model::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, Action>
     */
    protected function lifecycleActions(): array
    {
        return [
            $this->stageAction(),
            $this->pauseAction(),
            $this->resumeAction(),
            $this->closeAction(),
        ];
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
