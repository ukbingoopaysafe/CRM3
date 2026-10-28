<?php

namespace App\Services;

use App\Enums\Department;
use App\Enums\RequestStatus;
use App\Models\Base\Request;
use App\Models\Rental\Request as RentalRequest;
use App\Models\Resale\Request as ResaleRequest;

/**
 * The five operational indicators approved in D-057. Numbers are always
 * reported per department and never merged.
 */
class SummaryMetrics
{
    /**
     * @return array{active: int, overdue: int, due_today: int, without_customer: int, paused: int}
     */
    public function for(Department $department): array
    {
        /** @var class-string<Request> $model */
        $model = $department === Department::Rental ? RentalRequest::class : ResaleRequest::class;
        $query = $model::query();

        return [
            'active' => (clone $query)->where('status', RequestStatus::Active->value)->count(),
            'overdue' => (clone $query)->active()->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<', now())->count(),
            'due_today' => (clone $query)->active()
                ->whereBetween('next_follow_up_at', [now(), now()->endOfDay()])->count(),
            'without_customer' => (clone $query)->active()->whereNull('customer_id')->count(),
            'paused' => (clone $query)->where('status', RequestStatus::Paused->value)->count(),
        ];
    }
}
