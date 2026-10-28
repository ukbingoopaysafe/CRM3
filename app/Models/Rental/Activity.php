<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\Activity as BaseActivity;

class Activity extends BaseActivity
{
    protected $table = 'rental_activities';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
