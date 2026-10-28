<?php

namespace App\Services;

use App\Enums\Department;
use App\Enums\Role;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The single write path for staff accounts (D-080). The team screen and the
 * CLI command both go through it, so the role rules, the department grant and
 * the audit trail cannot drift between the two entry points (D-034, D-066).
 */
class UserAccountService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * A null actor means the console created the account before anyone could
     * sign in; there is no manager to prove yet.
     *
     * @param  array{name?: string, email: string, password: string, role: Role|string, departments?: array<int, Department|string>, can_manage_areas?: bool, job_title?: string|null, phone?: string|null}  $data
     */
    public function create(array $data, ?User $actor = null): User
    {
        $this->assertManager($actor);

        return DB::transaction(function () use ($data, $actor): User {
            $role = $this->role($data['role']);
            $departments = $this->departments($data['departments'] ?? []);
            $this->assertDepartmentsFitRole($role, $departments);

            $email = $this->normalizeEmail($data['email']);
            $this->assertEmailAvailable($email);

            if (blank($data['password'] ?? null)) {
                throw ValidationException::withMessages(['password' => 'كلمة المرور مطلوبة.']);
            }

            $user = User::query()->create([
                'name' => trim((string) ($data['name'] ?? '')) ?: Str::before($email, '@'),
                'email' => $email,
                'password' => (string) $data['password'],
                'role' => $role,
                'job_title' => $data['job_title'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            $this->syncDepartments($user, $role, $departments, (bool) ($data['can_manage_areas'] ?? false));

            $user->unsetRelation('memberships');

            $this->audit->record('user_created', $user, null, [
                'role' => $role->value,
                'departments' => $this->departmentValues($departments),
            ], actor: $actor);

            return $user;
        });
    }

    /**
     * @param  array{name?: string, email?: string, password?: string|null, role?: Role|string, departments?: array<int, Department|string>, can_manage_areas?: bool, job_title?: string|null, phone?: string|null}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        Authorization::requireManager($actor);

        return DB::transaction(function () use ($user, $data, $actor): User {
            $role = array_key_exists('role', $data) ? $this->role($data['role']) : $user->role;

            if ($role !== Role::Manager && $actor->is($user)) {
                throw ValidationException::withMessages([
                    'role' => 'لا يمكنك إزالة دور مدير الشركة عن حسابك.',
                ]);
            }

            $departments = array_key_exists('departments', $data)
                ? $this->departments($data['departments'] ?? [])
                : $this->existingDepartments($user);

            $this->assertDepartmentsFitRole($role, $departments);

            $email = array_key_exists('email', $data)
                ? $this->normalizeEmail($data['email'])
                : $user->email;

            $this->assertEmailAvailable($email, $user->getKey());

            $before = $this->snapshot($user);

            $user->fill([
                'name' => trim((string) ($data['name'] ?? $user->name)),
                'email' => $email,
                'role' => $role,
                'job_title' => $data['job_title'] ?? $user->job_title,
                'phone' => $data['phone'] ?? $user->phone,
            ]);

            if (filled($data['password'] ?? null)) {
                $user->password = (string) $data['password'];
            }

            $user->save();

            $grant = array_key_exists('can_manage_areas', $data)
                ? (bool) $data['can_manage_areas']
                : null;

            $this->syncDepartments($user, $role, $departments, $grant);

            $user->unsetRelation('memberships')->refresh();

            $this->audit->record('user_updated', $user, null, [
                'before' => $before,
                'after' => $this->snapshot($user),
                'departments' => $this->departmentValues($departments),
            ], actor: $actor);

            return $user;
        });
    }

    /**
     * Deactivation ends panel access on the next request, because Filament
     * re-checks canAccessPanel() on every one of them.
     */
    public function setActive(User $user, bool $active, User $actor): User
    {
        Authorization::requireManager($actor);

        if (! $active && $actor->is($user)) {
            throw ValidationException::withMessages([
                'actor' => 'لا يمكنك تعطيل الحساب الذي تعمل عليه.',
            ]);
        }

        if ($user->is_active === $active) {
            return $user;
        }

        $user->forceFill(['is_active' => $active])->save();

        $this->audit->record($active ? 'user_activated' : 'user_deactivated', $user, null, [
            'email' => $user->email,
        ], actor: $actor);

        return $user;
    }

    /**
     * @throws ValidationException
     */
    public function assertEmailAvailable(string $email, ?int $ignoreId = null): void
    {
        $taken = User::query()
            ->where('email', $this->normalizeEmail($email))
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'email' => 'يوجد حساب بهذا البريد الإلكتروني بالفعل.',
            ]);
        }
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function assertManager(?User $actor): void
    {
        if ($actor !== null) {
            Authorization::requireManager($actor);
        }
    }

    private function role(Role|string $role): Role
    {
        return $role instanceof Role ? $role : Role::from($role);
    }

    /**
     * @throws ValidationException
     */
    private function assertDepartmentsFitRole(Role $role, array $departments): void
    {
        if ($role === Role::Employee && $departments === []) {
            throw ValidationException::withMessages([
                'departments' => 'الموظف يحتاج قسمًا واحدًا على الأقل.',
            ]);
        }
    }

    /**
     * @return array<int, Department>
     */
    private function departments(array $values): array
    {
        $departments = [];

        foreach ($values as $value) {
            $department = $value instanceof Department ? $value : Department::from((string) $value);

            $departments[$department->value] = $department;
        }

        return array_values($departments);
    }

    /**
     * @return array<int, Department>
     */
    private function existingDepartments(User $user): array
    {
        return $user->memberships
            ->pluck('department')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * A manager always covers both departments, so their memberships are
     * cleared instead of being kept stale (D-063).
     *
     * @param  array<int, Department>  $departments
     */
    private function syncDepartments(User $user, Role $role, array $departments, ?bool $canManageAreas): void
    {
        if ($role === Role::Manager) {
            $user->memberships()->delete();

            return;
        }

        $keep = $this->departmentValues($departments);

        $user->memberships()->whereNotIn('department', $keep)->delete();

        foreach ($departments as $department) {
            $membership = $user->memberships()->firstOrNew(['department' => $department->value]);

            if ($canManageAreas !== null) {
                $membership->can_manage_areas = $canManageAreas;
            }

            $membership->save();
        }
    }

    /**
     * @param  array<int, Department>  $departments
     * @return array<int, string>
     */
    private function departmentValues(array $departments): array
    {
        return array_map(fn (Department $department): string => $department->value, $departments);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'job_title' => $user->job_title,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
        ];
    }
}
