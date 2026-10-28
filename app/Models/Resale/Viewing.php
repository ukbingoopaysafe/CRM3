<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\Viewing as BaseViewing;

class Viewing extends BaseViewing
{
    protected $table = 'resale_viewings';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
