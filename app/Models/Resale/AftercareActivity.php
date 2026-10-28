<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\AftercareActivity as BaseAftercareActivity;

class AftercareActivity extends BaseAftercareActivity
{
    protected $table = 'resale_aftercare_activities';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
