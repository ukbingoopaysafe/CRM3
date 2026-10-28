<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\LeadSource as BaseLeadSource;

class LeadSource extends BaseLeadSource
{
    protected $table = 'resale_lead_sources';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
