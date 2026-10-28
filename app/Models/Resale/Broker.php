<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\Broker as BaseBroker;

class Broker extends BaseBroker
{
    protected $table = 'resale_brokers';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
