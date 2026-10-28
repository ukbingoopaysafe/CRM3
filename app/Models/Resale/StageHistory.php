<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\StageHistory as BaseStageHistory;

class StageHistory extends BaseStageHistory
{
    protected $table = 'resale_stage_histories';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
