<?php

namespace App\Models;

use App\Enums\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only audit trail (D-061, D-071). Rows cannot be edited or deleted
 * from the interface, and free-text notes are never copied into it.
 *
 * @property Department|null $department
 * @property array<string, mixed>|null $payload
 */
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForDepartment(Builder $query, Department $department): Builder
    {
        return $query->where('department', $department->value);
    }

    /**
     * Employees only see their own events; managers see the whole department
     * trail (D-027, D-071).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isManager()) {
            return $query;
        }

        return $query->where('actor_user_id', $user->id);
    }

    public function summary(): string
    {
        return match ($this->event_type) {
            'created' => 'إنشاء',
            'updated' => 'تعديل',
            'archived' => 'أرشفة',
            'restored' => 'استعادة',
            'deleted' => 'حذف',
            'stage_changed' => 'تغيير المرحلة',
            'status_changed' => 'تغيير الحالة',
            'owner_changed' => 'تغيير المسؤول',
            'reassigned' => 'إعادة تعيين',
            'activity_logged' => 'تسجيل تواصل',
            'deal_created' => 'إنشاء صفقة',
            default => $this->event_type,
        };
    }
}
