<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\DealCommission as BaseDealCommission;

class DealCommission extends BaseDealCommission
{
    protected $table = 'resale_deal_commissions';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
