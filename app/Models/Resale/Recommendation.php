<?php

namespace App\Models\Resale;

use App\Enums\Department;
use App\Models\Base\Recommendation as BaseRecommendation;

class Recommendation extends BaseRecommendation
{
    protected $table = 'resale_recommendations';

    public static function department(): Department
    {
        return Department::Resale;
    }
}
