<?php

namespace App\Filament\Resources\Rental\Requests\Pages;

use App\Filament\Resources\Rental\Requests\RequestResource;
use App\Filament\Resources\Requests\Concerns\HandlesRequestWrites;
use App\Filament\Resources\Requests\Concerns\HasRequestLifecycleActions;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRequest extends EditRecord
{
    use HandlesRequestWrites;
    use HasRequestLifecycleActions;

    protected static string $resource = RequestResource::class;

    public function getTitle(): string
    {
        return 'تعديل بيانات الطلب';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تم حفظ التعديلات';
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            ViewAction::make()->label('عرض'),
            ...array_map(
                fn (Action $action): Action => $action->record($record),
                $this->lifecycleActions(),
            ),
        ];
    }
}
