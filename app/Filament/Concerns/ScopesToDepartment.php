<?php

namespace App\Filament\Concerns;

/**
 * Hides a resource from users who do not work in its department. The policy
 * still guards each record; this only keeps the navigation honest (D-020).
 */
trait ScopesToDepartment
{
    public static function canAccess(): bool
    {
        $user = auth()->user();
        $model = static::getModel();

        return $user !== null
            && is_string($model)
            && method_exists($model, 'department')
            && $user->belongsToDepartment($model::department());
    }
}
