<?php

namespace App\Services;

use App\Enums\ContactParty;
use App\Enums\Department;
use App\Enums\Governorate;
use App\Models\Base\Customer;
use App\Models\Base\Request;
use App\Models\Rental;
use App\Models\Resale;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Exports the columns D-062 allows, and only the rows the actor may read:
 * customers and requests of one department. Activities, deals and
 * commissions stay out (D-023).
 */
class ExcelExportService
{
    public const CUSTOMER_COLUMNS = ['الاسم', 'الهاتف', 'الجنسية', 'الحالة', 'أُضيف بواسطة', 'تاريخ الإضافة'];

    public const REQUEST_COLUMNS = [
        'رقم الطلب',
        'جهة الاتصال',
        'الاسم',
        'الهاتف',
        'المحافظة',
        'المنطقة',
        'المرحلة',
        'الحالة',
        'المسؤول',
        'المتابعة القادمة',
        'تاريخ الإنشاء',
        'تاريخ الإغلاق',
    ];

    private const DIRECTORY = 'exports';

    private const RETENTION_MINUTES = 60;

    public function customers(Department $department, User $actor): string
    {
        $this->assertScope($department, $actor);

        /** @var class-string<Customer> $model */
        $model = app(CustomerService::class)->modelFor($department);

        return $this->write("customers-{$department->value}", self::CUSTOMER_COLUMNS, function (callable $add) use ($model): void {
            $model::query()->with('creator')->orderBy('id')->lazyById(500)->each(
                fn (Customer $customer) => $add([
                    $customer->name,
                    $customer->phone,
                    $customer->nationality,
                    $customer->isArchived() ? 'مؤرشف' : 'نشط',
                    $customer->creator?->name,
                    $customer->created_at?->format('Y-m-d H:i'),
                ]),
            );
        });
    }

    public function requests(Department $department, User $actor): string
    {
        $this->assertScope($department, $actor);

        /** @var class-string<Request> $model */
        $model = $department === Department::Rental ? Rental\Request::class : Resale\Request::class;

        return $this->write("requests-{$department->value}", self::REQUEST_COLUMNS, function (callable $add) use ($model): void {
            $model::query()
                ->with(['customer', 'broker', 'area', 'owner'])
                ->orderBy('id')
                ->lazyById(500)
                ->each(fn (Request $request) => $add([
                    $request->getKey(),
                    $request->contact_party->getLabel(),
                    $request->contactName(),
                    $request->contact_party === ContactParty::Broker
                        ? ($request->broker_phone_snapshot ?? $request->broker?->phone)
                        : $request->customer?->phone,
                    Governorate::tryFrom((string) $request->governorate)?->getLabel(),
                    $request->area?->name,
                    $request->stage?->getLabel(),
                    $request->status?->getLabel(),
                    $request->owner?->name,
                    $request->next_follow_up_at?->format('Y-m-d H:i'),
                    $request->created_at?->format('Y-m-d H:i'),
                    $request->closed_at?->format('Y-m-d H:i'),
                ]));
        });
    }

    /**
     * The empty .xlsx the manager hands to whoever fills the data, so the
     * header never has to be guessed (D-056).
     */
    public function customerTemplate(): string
    {
        return $this->write('customer-import-template', CustomerImportService::COLUMNS, fn () => null);
    }

    private function write(string $basename, array $headers, \Closure $rows): string
    {
        $directory = Storage::disk('local')->path(self::DIRECTORY);
        File::ensureDirectoryExists($directory);
        $this->prune($directory);

        $path = $directory.'/'.$basename.'-'.now()->format('Ymd-His').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers, (new Style)->setFontBold()));
        $rows(function (array $values) use ($writer): void {
            $writer->addRow(Row::fromValues($values));
        });
        $writer->close();

        return $path;
    }

    /**
     * Downloads are one-shot files; anything from the previous hour is stale.
     */
    private function prune(string $directory): void
    {
        $cutoff = now()->subMinutes(self::RETENTION_MINUTES)->getTimestamp();

        foreach (File::files($directory) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertScope(Department $department, User $actor): void
    {
        if (! $actor->belongsToDepartment($department)) {
            throw ValidationException::withMessages([
                'department' => 'لا يمكنك تصدير بيانات قسم غير مصرح لك به.',
            ]);
        }
    }
}
