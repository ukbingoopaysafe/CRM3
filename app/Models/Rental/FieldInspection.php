<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\FieldInspection as BaseFieldInspection;

class FieldInspection extends BaseFieldInspection
{
    protected $table = 'rental_field_inspections';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
