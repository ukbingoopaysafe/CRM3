<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\DealCommission as BaseDealCommission;

class DealCommission extends BaseDealCommission
{
    protected $table = 'rental_deal_commissions';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
