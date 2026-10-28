<?php

namespace App\Filament\Resources\Rental\Requests\Pages;

use App\Filament\Resources\Rental\Requests\RequestResource;
use App\Filament\Resources\Requests\Concerns\HasDealAction;
use App\Filament\Resources\Requests\Concerns\HasReassignmentActions;
use App\Filament\Resources\Requests\Concerns\HasRequestLifecycleActions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRequest extends ViewRecord
{
    use HasDealAction;
    use HasReassignmentActions;
    use HasRequestLifecycleActions;

    protected static string $resource = RequestResource::class;

    public function getTitle(): string
    {
        return 'طلب #'.$this->getRecord()->getKey();
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            EditAction::make()->label('تعديل'),
            ...array_map(
                fn (Action $action): Action => $action->record($record),
                $this->lifecycleActions(),
            ),
            $this->dealAction()->record($record),
            $this->requestReassignmentAction()->record($record),
            $this->directReassignmentAction()->record($record),
            $this->activityAction()->record($record),
        ];
    }
}
