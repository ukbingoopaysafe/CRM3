<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\Deal as BaseDeal;

class Deal extends BaseDeal
{
    protected $table = 'resale_deals';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
