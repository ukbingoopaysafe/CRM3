<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\CloseReason as BaseCloseReason;

class CloseReason extends BaseCloseReason
{
    protected $table = 'rental_close_reasons';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
