<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\Request as BaseRequest;

class Request extends BaseRequest
{
    protected $table = 'rental_requests';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
