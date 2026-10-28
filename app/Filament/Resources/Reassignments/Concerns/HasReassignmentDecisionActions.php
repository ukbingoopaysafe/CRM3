<?php

namespace App\Filament\Resources\Reassignments\Concerns;

use App\Enums\ReassignmentStatus;
use App\Filament\Concerns\ReportsDomainErrors;
use App\Models\Base\Reassignment;
use App\Services\ReassignmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The three decisions of D-027/D-070: the manager approves or rejects, and the
 * requester may withdraw before either happens.
 */
trait HasReassignmentDecisionActions
{
    use ReportsDomainErrors;

    protected function approveAction(): Action
    {
        return Action::make('approve')
            ->label('موافقة')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Reassignment $record): bool => $record->status === ReassignmentStatus::Pending
                && (auth()->user()?->can('decide', $record) ?? false))
            ->modalHeading('الموافقة على إعادة التعيين')
            ->modalDescription('ينتقل مسؤول الطلب إلى الموظف المستهدف فور الموافقة، وتظل الأنشطة المكتملة بمنفذيها.')
            ->schema([
                Textarea::make('note')->label('ملاحظة القرار (اختيارية)')->rows(2),
            ])
            ->action(function (Reassignment $record, array $data): void {
                $this->runReassignmentAction(
                    fn () => app(ReassignmentService::class)->approve($record, auth()->user(), $data['note'] ?? null),
                    'تمت الموافقة ونُقل مسؤول الطلب',
                );
            });
    }

    protected function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('رفض')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Reassignment $record): bool => $record->status === ReassignmentStatus::Pending
                && (auth()->user()?->can('decide', $record) ?? false))
            ->modalHeading('رفض طلب إعادة التعيين')
            ->modalDescription('يبقى المسؤول الحالي كما هو، ويُغلق الطلب برفض مسجل.')
            ->schema([
                Textarea::make('note')->label('سبب الرفض (اختياري)')->rows(2),
            ])
            ->action(function (Reassignment $record, array $data): void {
                $this->runReassignmentAction(
                    fn () => app(ReassignmentService::class)->reject($record, auth()->user(), $data['note'] ?? null),
                    'تم رفض طلب إعادة التعيين',
                );
            });
    }

    protected function cancelAction(): Action
    {
        return Action::make('cancelReassignment')
            ->label('إلغاء الطلب')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Reassignment $record): bool => $record->status === ReassignmentStatus::Pending
                && (auth()->user()?->can('cancel', $record) ?? false))
            ->modalHeading('إلغاء طلب إعادة التعيين')
            ->modalDescription('يبقى المسؤول الحالي كما هو، ويُسجل الإلغاء في سجل التدقيق.')
            ->action(function (Reassignment $record): void {
                $this->runReassignmentAction(
                    fn () => app(ReassignmentService::class)->cancel($record, auth()->user()),
                    'تم إلغاء طلب إعادة التعيين',
                );
            });
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
