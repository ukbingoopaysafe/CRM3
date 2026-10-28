<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\Reassignment as BaseReassignment;

class Reassignment extends BaseReassignment
{
    protected $table = 'resale_reassignments';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
