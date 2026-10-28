<?php

namespace App\Filament\Resources\Customers\Concerns;

use App\Services\CustomerService;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes go through the domain service so normalization, duplicate blocking,
 * archival rules and the audit trail apply to the interface too (D-028).
 */
trait HandlesCustomerWrites
{
    protected function handleRecordCreation(array $data): Model
    {
        return app(CustomerService::class)->create(
            static::getResource()::getModel(),
            $data,
            auth()->user(),
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(CustomerService::class)->update($record, $data, auth()->user());
    }
}
