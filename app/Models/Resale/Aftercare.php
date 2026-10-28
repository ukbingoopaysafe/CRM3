<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\Aftercare as BaseAftercare;

class Aftercare extends BaseAftercare
{
    protected $table = 'resale_aftercares';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
