<?php

namespace App\Models\Base;

use App\Enums\RequestStatus;
use App\Enums\Stage;
use App\Models\Concerns\BelongsToDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records every stage or status change so the workflow history is auditable
 * (D-048). Rows are only appended.
 */
abstract class StageHistory extends Model
{
    use BelongsToDepartment;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'from_stage' => Stage::class,
            'to_stage' => Stage::class,
            'from_status' => RequestStatus::class,
            'to_status' => RequestStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(static::related('Request'), 'request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
