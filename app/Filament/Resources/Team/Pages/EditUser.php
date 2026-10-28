<?php

namespace App\Filament\Resources\Team\Pages;

use App\Filament\Resources\Team\Concerns\HandlesUserWrites;
use App\Filament\Resources\Team\TeamResource;
use App\Models\DepartmentMembership;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use HandlesUserWrites;

    protected static string $resource = TeamResource::class;

    public function getTitle(): string
    {
        return 'تعديل الحساب';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تم حفظ التعديلات';
    }

    /**
     * Departments and the area grant live on the membership rows, not on the
     * user, so the form has to be fed from them explicitly.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['departments'] = $this->record->memberships
            ->map(fn (DepartmentMembership $membership): string => $membership->department->value)
            ->all();

        $data['can_manage_areas'] = $this->record->memberships
            ->contains(fn (DepartmentMembership $membership): bool => $membership->can_manage_areas);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
