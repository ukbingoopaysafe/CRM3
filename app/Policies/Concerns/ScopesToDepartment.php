<?php

namespace App\Policies\Concerns;

use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Every record lives in exactly one department, so one check answers "may this
 * user touch this record at all?" (D-020, PERMISSIONS.md §4.1).
 */
trait ScopesToDepartment
{
    protected function inDepartment(User $user, Model $record): bool
    {
        /** @var class-string<BelongsToDepartment&Model> $class */
        $class = $record::class;

        return $user->belongsToDepartment($class::department());
    }
}
