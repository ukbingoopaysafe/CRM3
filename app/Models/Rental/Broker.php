<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\Broker as BaseBroker;

class Broker extends BaseBroker
{
    protected $table = 'rental_brokers';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
