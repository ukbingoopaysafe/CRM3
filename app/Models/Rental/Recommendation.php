<?php

namespace App\Models\Rental;

use App\Enums\Department;
use App\Models\Base\Recommendation as BaseRecommendation;

class Recommendation extends BaseRecommendation
{
    protected $table = 'rental_recommendations';

    public static function department(): Department
    {
        return Department::Rental;
    }
}
