<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\Deal as BaseDeal;

class Deal extends BaseDeal
{
    protected $table = 'rental_deals';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
