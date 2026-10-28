<?php

namespace App\Filament\Resources\Team\Concerns;

use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Both pages write through UserAccountService so the console and the panel
 * share one set of rules and one audit trail (D-066, D-080).
 */
trait HandlesUserWrites
{
    protected function handleRecordCreation(array $data): Model
    {
        return $this->throughService(
            fn (): User => app(UserAccountService::class)->create($data, auth()->user()),
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        return $this->throughService(
            fn (): User => app(UserAccountService::class)->update($record, $data, auth()->user()),
        );
    }

    /**
     * Form fields validate under "data.", so a domain error keyed on the field
     * name would otherwise be stored where no input can display it.
     */
    private function throughService(\Closure $callback): User
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field): array => ["data.{$field}" => $messages])
                    ->all(),
            );
        }
    }
}
