<?php

namespace App\Console\Commands;

use App\Enums\Department;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a staff account before the team screen exists (D-080, D-034).
 * An employee must belong to at least one department; a manager always covers
 * both, so a department option is meaningless for that role (D-063).
 */
class CreateUser extends Command
{
    protected $signature = 'users:create
        {email : البريد الإلكتروني}
        {--name= : الاسم الظاهر}
        {--password= : كلمة المرور؛ تُولد تلقائيًا إذا لم تُحدد}
        {--role=employee : الدور: employee أو manager}
        {--department=* : قسم الموظف: rental و/أو resale}
        {--job-title= : المسمى الوظيفي}
        {--phone= : رقم الهاتف}';

    protected $description = 'إنشاء حساب موظف أو مدير شركة';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('البريد الإلكتروني غير صالح.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('يوجد حساب بهذا البريد الإلكتروني بالفعل.');

            return self::FAILURE;
        }

        $role = Role::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error('الدور يجب أن يكون employee أو manager.');

            return self::FAILURE;
        }

        $departments = $this->departments();

        if ($departments === null) {
            $this->error('قيمة القسم يجب أن تكون rental أو resale.');

            return self::FAILURE;
        }

        if ($role === Role::Employee && $departments === []) {
            $this->error('الموظف يحتاج قسمًا واحدًا على الأقل: rental أو resale.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(16, symbols: false));
        $generated = blank($this->option('password'));

        DB::transaction(function () use ($email, $role, $departments, $password): void {
            $user = User::query()->create([
                'name' => (string) ($this->option('name') ?: Str::before($email, '@')),
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'job_title' => $this->option('job-title'),
                'phone' => $this->option('phone'),
                'is_active' => true,
            ]);

            foreach ($role === Role::Employee ? $departments : [] as $department) {
                $user->memberships()->create(['department' => $department]);
            }
        });

        $this->info("تم إنشاء الحساب: {$email} ({$role->getLabel()})");

        if ($generated) {
            $this->warn("كلمة المرور المؤقتة: {$password} — غيّرها بعد أول دخول.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, Department>|null
     */
    private function departments(): ?array
    {
        $departments = [];

        foreach ((array) $this->option('department') as $value) {
            $department = Department::tryFrom((string) $value);

            if ($department === null) {
                return null;
            }

            $departments[$department->value] = $department;
        }

        return array_values($departments);
    }
}
