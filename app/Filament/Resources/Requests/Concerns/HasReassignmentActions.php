<?php

namespace App\Filament\Resources\Requests\Concerns;

use App\Enums\Role;
use App\Filament\Concerns\ReportsDomainErrors;
use App\Models\Base\Request;
use App\Models\User;
use App\Services\ReassignmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Two ways to move an open request: the employee asks for a specific colleague
 * and waits, or the company manager transfers it directly. Both require a
 * reason and let the service write the audit entry (D-027, D-070).
 */
trait HasReassignmentActions
{
    use ReportsDomainErrors;

    protected function requestReassignmentAction(): Action
    {
        return Action::make('requestReassignment')
            ->label('طلب إعادة تعيين')
            ->icon('heroicon-o-arrows-right-left')
            ->color('warning')
            ->visible(fn (Request $record): bool => $this->canBeReassigned($record)
                && (auth()->user()?->can('requestReassignment', $record) ?? false))
            ->modalHeading('طلب إعادة تعيين مسؤول الطلب')
            ->modalDescription('يبقى المسؤول الحالي مسؤولًا حتى يقرر المدير. لا يكون مدير الشركة هدفًا للطلب.')
            ->schema(fn (Request $record): array => $this->reassignmentSchema($record))
            ->action(function (Request $record, array $data): void {
                $this->runReassignmentAction(
                    fn () => app(ReassignmentService::class)->request(
                        $record,
                        (int) $data['target_user_id'],
                        (string) $data['reason'],
                        auth()->user(),
                    ),
                    'تم إرسال طلب إعادة التعيين',
                );
            });
    }

    protected function directReassignmentAction(): Action
    {
        return Action::make('directReassignment')
            ->label('إعادة تعيين مباشرة')
            ->icon('heroicon-o-bolt')
            ->color('danger')
            ->visible(fn (Request $record): bool => $this->canBeReassigned($record)
                && (auth()->user()?->can('reassignDirect', $record) ?? false))
            ->modalHeading('إعادة تعيين مباشرة')
            ->modalDescription('ينتقل المسؤول فور التنفيذ دون قبول من الموظف الجديد، ويُسجل السبب في سجل التدقيق.')
            ->schema(fn (Request $record): array => $this->reassignmentSchema($record))
            ->action(function (Request $record, array $data): void {
                $this->runReassignmentAction(
                    fn () => app(ReassignmentService::class)->direct(
                        $record,
                        (int) $data['target_user_id'],
                        (string) $data['reason'],
                        auth()->user(),
                    ),
                    'تمت إعادة تعيين المسؤول',
                );
            });
    }

    private function canBeReassigned(Request $record): bool
    {
        return ! $record->isClosed() && $record->pendingReassignment === null;
    }

    /**
     * @return array<int, Select|Textarea>
     */
    private function reassignmentSchema(Request $record): array
    {
        return [
            Select::make('target_user_id')
                ->label('الموظف المستهدف')
                ->options(fn (): array => $this->transferTargets($record))
                ->searchable()
                ->required()
                ->helperText('موظفون نشطون في القسم نفسه، عدا المسؤول الحالي.'),

            Textarea::make('reason')
                ->label('السبب')
                ->rows(2)
                ->required(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function transferTargets(Request $record): array
    {
        return User::query()
            ->active()
            ->where('role', Role::Employee->value)
            ->whereHas('memberships', fn (Builder $query) => $query
                ->where('department', $record::department()->value))
            ->when($record->owner_user_id, fn (Builder $query) => $query->whereKeyNot($record->owner_user_id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function runReassignmentAction(\Closure $callback, string $successMessage): void
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
