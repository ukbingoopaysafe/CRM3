<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\AftercareActivity as BaseAftercareActivity;

class AftercareActivity extends BaseAftercareActivity
{
    protected $table = 'rental_aftercare_activities';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
