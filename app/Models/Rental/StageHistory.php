<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\StageHistory as BaseStageHistory;

class StageHistory extends BaseStageHistory
{
    protected $table = 'rental_stage_histories';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
