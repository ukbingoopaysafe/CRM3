<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\Request as BaseRequest;

class Request extends BaseRequest
{
    protected $table = 'resale_requests';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
