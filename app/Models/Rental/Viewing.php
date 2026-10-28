<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\Viewing as BaseViewing;

class Viewing extends BaseViewing
{
    protected $table = 'rental_viewings';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
