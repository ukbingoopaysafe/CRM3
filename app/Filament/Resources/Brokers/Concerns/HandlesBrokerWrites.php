<?php

namespace App\Filament\Resources\Brokers\Concerns;

use App\Services\BrokerService;
use Illuminate\Database\Eloquent\Model;

/**
 * Normalisation, the duplicate-phone block and the audit trail belong to the
 * service, so the panel writes through it instead of the model (D-026).
 */
trait HandlesBrokerWrites
{
    protected function handleRecordCreation(array $data): Model
    {
        return app(BrokerService::class)->create(
            static::getResource()::getModel(),
            $data,
            auth()->user(),
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(BrokerService::class)->update($record, $data, auth()->user());
    }
}
