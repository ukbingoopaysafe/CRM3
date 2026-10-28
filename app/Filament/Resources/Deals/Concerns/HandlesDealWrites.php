<?php

namespace App\Filament\Resources\Deals\Concerns;

use App\Enums\Department;
use App\Models\Rental;
use App\Models\Resale;
use App\Services\DealService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The service owns the "closed, successful, once only" rule and writes the
 * commission lines with the deal, so the panel never inserts a deal itself
 * (D-058).
 */
trait HandlesDealWrites
{
    protected function handleRecordCreation(array $data): Model
    {
        $department = static::getResource()::getModel()::department();
        $requestModel = $department === Department::Rental ? Rental\Request::class : Resale\Request::class;

        $request = $requestModel::query()->find((int) ($data['request_id'] ?? 0));

        if ($request === null) {
            throw ValidationException::withMessages([
                'request_id' => 'الطلب المحدد غير موجود في هذا القسم.',
            ]);
        }

        unset($data['request_id']);

        return app(DealService::class)->create($request, $data, auth()->user());
    }
}
