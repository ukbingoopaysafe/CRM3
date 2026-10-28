<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\Customer as BaseCustomer;

class Customer extends BaseCustomer
{
    protected $table = 'rental_customers';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
