<?php

namespace App\Filament\Resources\Requests\Concerns;

use App\Services\RequestService;
use Illuminate\Database\Eloquent\Model;

/**
 * The service owns the contact-party rule, the follow-up requirement and the
 * first stage history row, so the panel writes through it (D-043, D-049).
 */
trait HandlesRequestWrites
{
    protected function handleRecordCreation(array $data): Model
    {
        return app(RequestService::class)->create(
            static::getResource()::getModel(),
            $data,
            auth()->user(),
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(RequestService::class)->update($record, $data, auth()->user());
    }
}
