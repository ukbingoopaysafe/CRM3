<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\Aftercare as BaseAftercare;

class Aftercare extends BaseAftercare
{
    protected $table = 'rental_aftercares';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
