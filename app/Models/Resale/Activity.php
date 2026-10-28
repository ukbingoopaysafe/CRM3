<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\Activity as BaseActivity;

class Activity extends BaseActivity
{
    protected $table = 'resale_activities';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
