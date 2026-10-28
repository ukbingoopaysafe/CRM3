<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\Base\Customer;
use App\Models\User;
use App\Support\ImportSummary;
use App\Support\Phone;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Creates customers from the fixed .xlsx template in D-056: name, phone,
 * country code, nationality. Rows are independent, so one bad row never
 * stops the rest (D-024). The phone policy and the duplicate rule stay in
 * CustomerService, never duplicated here (D-028, D-029).
 */
class CustomerImportService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MAX_ROWS = 5000;

    public const COLUMNS = ['اسم العميل', 'رقم الهاتف', 'رمز الدولة', 'الجنسية'];

    private const REPORT_DIRECTORY = 'exports';

    public function __construct(private readonly CustomerService $customers) {}

    /**
     * @throws ValidationException when the file itself cannot be imported
     */
    public function import(Department $department, string $path, User $actor): ImportSummary
    {
        $this->assertFile($path);

        if (! $actor->belongsToDepartment($department)) {
            throw ValidationException::withMessages([
                'file' => 'لا يمكنك الاستيراد إلى قسم غير مصرح لك به.',
            ]);
        }

        $model = $this->customers->modelFor($department);

        $created = 0;
        $total = 0;
        $failures = [];
        $reader = new Reader;
        $rowNumber = 0;

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    // Openspout skips blank rows, so the reported number is the
                    // position among the rows that actually carry data.
                    $rowNumber++;

                    if ($rowNumber === 1) {
                        $this->assertHeader($row->toArray());

                        continue;
                    }

                    $total++;

                    if ($total > self::MAX_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => 'الملف يتجاوز الحد الأقصى '.self::MAX_ROWS.' صفًا.',
                        ]);
                    }

                    $cells = array_pad(array_map([$this, 'text'], $row->toArray()), count(self::COLUMNS), '');

                    if ($cells[0] === '' && $cells[1] === '') {
                        continue;
                    }

                    $reason = $this->createRow($model, $cells, $actor);

                    if ($reason === null) {
                        $created++;

                        continue;
                    }

                    $failures[] = ['row' => $rowNumber, 'reason' => $reason];
                }

                // Only the first sheet is imported; the template has one.
                break;
            }
        } finally {
            $reader->close();
        }

        return new ImportSummary(
            created: $created,
            total: $total,
            failures: $failures,
            reportPath: $failures === [] ? null : $this->writeReport($failures),
        );
    }

    /**
     * @param  class-string<Customer>  $model
     * @param  array<int, string>  $cells
     * @return string|null the failure reason, or null when the row was created
     */
    private function createRow(string $model, array $cells, User $actor): ?string
    {
        [$name, $phone, $country, $nationality] = $cells;

        if ($name === '') {
            return 'اسم العميل مطلوب.';
        }

        if ($phone === '') {
            return 'رقم الهاتف مطلوب.';
        }

        try {
            $similar = $this->customers->similarNames($model, $name);

            if ($similar !== []) {
                return 'الاسم يشبه سجلًا موجودًا ('.$similar[0]['name'].')؛ راجع ملف العميل قبل الاستيراد.';
            }

            $this->customers->create($model, [
                'name' => $name,
                'phone' => $phone,
                'phone_country' => $country === '' ? Phone::DEFAULT_COUNTRY : $country,
                'nationality' => $nationality === '' ? null : $nationality,
            ], $actor);
        } catch (ValidationException $exception) {
            return (string) collect($exception->errors())->flatten()->first();
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $header
     *
     * @throws ValidationException
     */
    private function assertHeader(array $header): void
    {
        $header = array_map([$this, 'text'], array_slice($header, 0, count(self::COLUMNS)));

        if ($header !== self::COLUMNS) {
            throw ValidationException::withMessages([
                'file' => 'ترويسة الملف يجب أن تكون: '.implode('، ', self::COLUMNS).'.',
            ]);
        }
    }

    private function assertFile(string $path): void
    {
        if (! is_file($path)) {
            throw ValidationException::withMessages(['file' => 'تعذّر العثور على الملف المرفوع.']);
        }

        if (filesize($path) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'حجم الملف يتجاوز 10 ميجابايت.']);
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw ValidationException::withMessages(['file' => 'الصيغة المقبولة هي .xlsx فقط.']);
        }
    }

    /**
     * @param  array<int, array{row: int, reason: string}>  $failures
     */
    private function writeReport(array $failures): string
    {
        $directory = Storage::disk('local')->path(self::REPORT_DIRECTORY);
        File::ensureDirectoryExists($directory);

        $path = $directory.'/import-errors-'.now()->format('Ymd-His').'-'.count($failures).'.csv';

        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['الصف', 'السبب']);

        foreach ($failures as $failure) {
            fputcsv($handle, [$failure['row'], $failure['reason']]);
        }

        fclose($handle);

        return $path;
    }

    private function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // Spreadsheets store a numeric phone as a float; 2.01012E+11 must
        // never reach the phone normaliser.
        if (is_float($value)) {
            return number_format($value, 0, '', '');
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return trim((string) $value);
    }
}
