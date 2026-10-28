<?php

namespace App\Filament\Resources\Areas\Concerns;

use App\Models\Area;
use App\Services\AreaService;
use Illuminate\Database\Eloquent\Model;

/**
 * Both pages write through AreaService so the normalised duplicate check and
 * the audit trail are the same as any other entry point (D-032, D-081).
 */
trait HandlesAreaWrites
{
    protected function handleRecordCreation(array $data): Model
    {
        return app(AreaService::class)->create($data, auth()->user());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Area $record */
        return app(AreaService::class)->update($record, $data, auth()->user());
    }
}
