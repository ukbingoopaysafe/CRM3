<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Resources\Rental\Customers\Pages\ListCustomers;
use App\Models\Rental\Customer;
use App\Services\CustomerImportService;
use App\Services\CustomerService;
use App\Services\ExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

/**
 * Excel is a one-way door in each direction (D-021): rows are created, never
 * matched or updated, and an export never leaves the actor's department
 * (D-023, D-024).
 */
class ExcelTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_valid_rows_are_created_and_the_failed_ones_come_back_with_their_numbers(): void
    {
        $employee = $this->employee(Department::Rental);
        $path = $this->workbook([
            ['سارة عبد الله', '+201012345678', 'EG', 'مصر'],
            ['', '+201012345679', 'EG', 'مصر'],
            ['حسن محمود', 'not-a-number', 'EG', 'مصر'],
            ['منى سامي', '+201012345680', 'EG', 'السعودية'],
        ]);

        $summary = app(CustomerImportService::class)->import(Department::Rental, $path, $employee);

        $this->assertSame(4, $summary->total);
        $this->assertSame(2, $summary->created);
        $this->assertSame([3, 4], array_column($summary->failures, 'row'));
        $this->assertSame(2, Customer::query()->count());
        $this->assertFileExists($summary->reportPath);
        $this->assertStringContainsString('الصف', File::get($summary->reportPath));

        File::delete([$path, $summary->reportPath]);
    }

    public function test_a_duplicate_phone_fails_instead_of_updating_the_existing_customer(): void
    {
        $employee = $this->employee(Department::Rental);
        app(CustomerService::class)->create(Customer::class, [
            'name' => 'سارة عبد الله',
            'phone' => '+201012345678',
        ], $employee);

        $path = $this->workbook([
            ['سارة عبد الله', '+201012345678', 'EG', 'مصر'],
        ]);

        $summary = app(CustomerImportService::class)->import(Department::Rental, $path, $employee);

        $this->assertSame(0, $summary->created);
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame('سارة عبد الله', Customer::query()->first()->name);

        File::delete([$path, $summary->reportPath]);
    }

    public function test_a_name_that_looks_like_an_existing_customer_is_sent_back_for_review(): void
    {
        $employee = $this->employee(Department::Rental);
        app(CustomerService::class)->create(Customer::class, [
            'name' => 'محمد أحمد علي',
            'phone' => '+201011111111',
        ], $employee);

        $path = $this->workbook([
            ['محمد أحمد علي', '+201022222222', 'EG', 'مصر'],
        ]);

        $summary = app(CustomerImportService::class)->import(Department::Rental, $path, $employee);

        $this->assertSame(0, $summary->created);
        $this->assertStringContainsString('يشبه', $summary->failures[0]['reason']);
        $this->assertSame(1, Customer::query()->count());

        File::delete([$path, $summary->reportPath]);
    }

    public function test_a_file_with_the_wrong_header_is_rejected_before_any_row_is_written(): void
    {
        $employee = $this->employee(Department::Rental);
        $path = $this->workbook([['سارة عبد الله', '+201012345678', 'EG', 'مصر']], ['الاسم', 'الهاتف']);

        try {
            app(CustomerImportService::class)->import(Department::Rental, $path, $employee);
            $this->fail('The import should reject a foreign header.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertSame(0, Customer::query()->count());

        File::delete($path);
    }

    public function test_an_employee_cannot_import_into_a_department_they_do_not_belong_to(): void
    {
        $employee = $this->employee(Department::Rental);
        $path = $this->workbook([['سارة عبد الله', '+201012345678', 'EG', 'مصر']]);

        $this->expectException(ValidationException::class);

        try {
            app(CustomerImportService::class)->import(Department::Resale, $path, $employee);
        } finally {
            File::delete($path);
        }
    }

    public function test_the_customer_export_holds_only_its_own_department_and_the_approved_columns(): void
    {
        $employee = $this->employee(Department::Rental);
        $resale = $this->employee(Department::Resale);

        app(CustomerService::class)->create(Customer::class, [
            'name' => 'سارة عبد الله',
            'phone' => '+201012345678',
            'nationality' => 'مصر',
        ], $employee);

        app(CustomerService::class)->create(\App\Models\Resale\Customer::class, [
            'name' => 'عميل إعادة البيع',
            'phone' => '+201099999999',
        ], $resale);

        $path = app(ExcelExportService::class)->customers(Department::Rental, $employee);
        $rows = $this->read($path);

        $this->assertSame(ExcelExportService::CUSTOMER_COLUMNS, $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame('سارة عبد الله', $rows[1][0]);
        $this->assertSame('+201012345678', $rows[1][1]);

        File::delete($path);
    }

    public function test_the_request_export_lists_the_contact_and_the_workflow_columns(): void
    {
        $request = $this->makeRequest();
        $path = app(ExcelExportService::class)->requests(Department::Rental, $request->owner);

        $rows = $this->read($path);

        $this->assertSame(ExcelExportService::REQUEST_COLUMNS, $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame($request->getKey(), (int) $rows[1][0]);
        $this->assertSame($request->customer->name, $rows[1][2]);

        File::delete($path);
    }

    public function test_the_import_template_carries_the_fixed_header(): void
    {
        $path = app(ExcelExportService::class)->customerTemplate();

        $this->assertSame([CustomerImportService::COLUMNS], $this->read($path));

        File::delete($path);
    }

    public function test_the_import_runs_from_the_customers_screen(): void
    {
        $employee = $this->employee(Department::Rental);
        $this->actingAs($employee);

        $path = $this->workbook([['سارة عبد الله', '+201012345678', 'EG', 'مصر']]);
        $relative = 'imports/'.basename($path);
        File::move($path, storage_path('app/private/'.$relative));

        Livewire::test(ListCustomers::class)
            ->callAction('importCustomers', data: ['file' => [$relative]])
            ->assertHasNoActionErrors();

        $this->assertSame(1, Customer::query()->count());

        File::delete(storage_path('app/private/'.$relative));
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, string>|null  $header
     */
    private function workbook(array $rows, ?array $header = null): string
    {
        $path = storage_path('app/private/imports/test-'.uniqid().'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($header ?? CustomerImportService::COLUMNS));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        return $rows;
    }
}
