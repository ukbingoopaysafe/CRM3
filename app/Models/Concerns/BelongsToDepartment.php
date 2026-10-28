<?php

namespace App\Models\Concerns;

use App\Enums\Department;

/**
 * Resolves sibling models of the same department by name, so a department
 * table can only ever point at another table of its own department (D-020).
 */
trait BelongsToDepartment
{
    abstract public static function department(): Department;

    /**
     * @param  class-string  $name  Short class name inside the department namespace.
     * @return class-string
     */
    protected static function related(string $name): string
    {
        $namespace = static::department() === Department::Rental
            ? 'App\\Models\\Rental\\'
            : 'App\\Models\\Resale\\';

        return $namespace.$name;
    }
}
