<?php

namespace App\Filament\Concerns;

use App\Enums\Department;
use App\Models\Concerns\BelongsToDepartment;
use App\Services\CustomerImportService;
use App\Services\ExcelExportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Every list page exports only its own department's records, and the import
 * button only exists where D-023 allows one: on the customer list. The size
 * and row limits belong to the services, not to the buttons (D-024, D-056).
 */
trait HasExcelActions
{
    use ReportsDomainErrors;

    protected function customerExcelActions(): ActionGroup
    {
        return ActionGroup::make([
            $this->importCustomersAction(),
            $this->exportCustomersAction(),
            $this->customerTemplateAction(),
        ])
            ->label('Excel')
            ->icon('heroicon-o-table-cells')
            ->button()
            ->color('gray');
    }

    protected function requestExcelActions(): ActionGroup
    {
        return ActionGroup::make([
            $this->exportRequestsAction(),
        ])
            ->label('Excel')
            ->icon('heroicon-o-table-cells')
            ->button()
            ->color('gray');
    }

    protected function importCustomersAction(): Action
    {
        $department = $this->excelDepartment();

        return Action::make('importCustomers')
            ->label('استيراد من Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('استيراد العملاء')
            ->modalDescription('ملف .xlsx بحد أقصى 10 ميجابايت و5,000 صف. يُنشأ كل صف صالح وحده، ويعود الفاشل في تقرير بأرقام الصفوف وأسبابها.')
            ->modalSubmitActionLabel('استيراد')
            ->schema([
                FileUpload::make('file')
                    ->label('ملف الاستيراد')
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->helperText('الترويسة بالترتيب: اسم العميل، رقم الهاتف، رمز الدولة، الجنسية. القالب جاهز من قائمة Excel نفسها.')
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(function (array $data) use ($department): mixed {
                $file = $data['file'] ?? null;
                $file = is_array($file) ? (string) reset($file) : (string) $file;

                try {
                    $summary = app(CustomerImportService::class)->import(
                        $department,
                        Storage::disk('local')->path($file),
                        auth()->user(),
                    );
                } catch (ValidationException $exception) {
                    $this->reportDomainError($exception);

                    return null;
                }

                $notification = Notification::make()
                    ->title($summary->hasFailures() ? 'تم الاستيراد مع صفوف فاشلة' : 'تم الاستيراد')
                    ->body("أُنشئ {$summary->created} من {$summary->total} صفًا، وفشل {$summary->failed()}.");

                $summary->hasFailures() ? $notification->warning() : $notification->success();
                $notification->send();

                return $summary->reportPath === null
                    ? null
                    : response()->download($summary->reportPath, 'import-errors.csv')->deleteFileAfterSend();
            });
    }

    protected function exportCustomersAction(): Action
    {
        $department = $this->excelDepartment();

        return Action::make('exportCustomers')
            ->label('تصدير العملاء')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn (): mixed => $this->download(
                app(ExcelExportService::class)->customers($department, auth()->user()),
                "customers-{$department->value}.xlsx",
            ));
    }

    protected function exportRequestsAction(): Action
    {
        $department = $this->excelDepartment();

        return Action::make('exportRequests')
            ->label('تصدير الطلبات')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn (): mixed => $this->download(
                app(ExcelExportService::class)->requests($department, auth()->user()),
                "requests-{$department->value}.xlsx",
            ));
    }

    protected function customerTemplateAction(): Action
    {
        return Action::make('customerTemplate')
            ->label('تنزيل قالب الاستيراد')
            ->icon('heroicon-o-document-arrow-down')
            ->action(fn (): mixed => $this->download(
                app(ExcelExportService::class)->customerTemplate(),
                'customer-import-template.xlsx',
            ));
    }

    private function download(string $path, string $name): mixed
    {
        return response()->download($path, $name)->deleteFileAfterSend();
    }

    private function excelDepartment(): Department
    {
        /** @var class-string<BelongsToDepartment> $model */
        $model = static::getResource()::getModel();

        return $model::department();
    }
}
