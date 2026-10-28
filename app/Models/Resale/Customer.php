<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\Customer as BaseCustomer;

class Customer extends BaseCustomer
{
    protected $table = 'resale_customers';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
