<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\LeadSource as BaseLeadSource;

class LeadSource extends BaseLeadSource
{
    protected $table = 'rental_lead_sources';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
