<?php

namespace App\Models;

use App\Enums\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explicit department membership of an employee (D-034). It also carries the
 * separately granted "can add areas" permission, which is never inferred from
 * the ability to create requests (D-032).
 *
 * @property Department $department
 * @property bool $can_manage_areas
 */
class DepartmentMembership extends Model
{
    protected $table = 'department_user';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'can_manage_areas' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
