<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Internal staff account. A company manager always covers both departments;
 * an employee only sees the departments assigned to them (D-034, D-063).
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'job_title',
        'phone',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(DepartmentMembership::class);
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager;
    }

    public function isEmployee(): bool
    {
        return $this->role === Role::Employee;
    }

    /**
     * Departments this user may operate on, resolved server-side.
     *
     * @return array<int, Department>
     */
    public function departmentScope(): array
    {
        if ($this->isManager()) {
            return Department::cases();
        }

        return $this->memberships->pluck('department')->all();
    }

    public function belongsToDepartment(Department $department): bool
    {
        return in_array($department, $this->departmentScope(), true);
    }

    public function canManageAreas(): bool
    {
        if ($this->isManager()) {
            return true;
        }

        return $this->memberships->contains(fn (DepartmentMembership $membership) => $membership->can_manage_areas);
    }

    public function canAccessDepartment(Department $department): bool
    {
        return $this->belongsToDepartment($department);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * TOTP stays optional (D-079); the team screen shows whether it is on.
     */
    public function hasMultiFactorAuthentication(): bool
    {
        return filled($this->getAppAuthenticationSecret());
    }

    public function scopeEmployees(Builder $query): Builder
    {
        return $query->where('role', Role::Employee->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Active users who may own a request of the department: the manager and
     * every employee who explicitly belongs to it (D-034, D-063).
     */
    public function scopeInDepartment(Builder $query, Department $department): Builder
    {
        return $query->active()->where(function (Builder $query) use ($department): void {
            $query->where('role', Role::Manager->value)
                ->orWhereHas('memberships', fn (Builder $memberships) => $memberships
                    ->where('department', $department->value));
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }
}
