<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\CloseReason as BaseCloseReason;

class CloseReason extends BaseCloseReason
{
    protected $table = 'resale_close_reasons';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
