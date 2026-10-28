<?php

namespace App\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Domain services answer with Arabic ValidationExceptions. Filament forms show
 * them inline; actions need them surfaced as a notification instead.
 */
trait ReportsDomainErrors
{
    protected function reportDomainError(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('تعذّر إتمام العملية')
            ->body(collect($exception->errors())->flatten()->first())
            ->send();
    }
}
