<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\FieldInspection as BaseFieldInspection;

class FieldInspection extends BaseFieldInspection
{
    protected $table = 'resale_field_inspections';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
