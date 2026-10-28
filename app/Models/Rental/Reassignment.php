<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\Reassignment as BaseReassignment;

class Reassignment extends BaseReassignment
{
    protected $table = 'rental_reassignments';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
