<?php

namespace App\Console\Commands;

use App\Enums\Department;
use App\Enums\Role;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates a staff account before anyone can sign in to the panel (D-080).
 * An employee must belong to at least one department; a manager always covers
 * both, so a department option is meaningless for that role (D-063). The
 * write itself goes through UserAccountService, exactly as the team screen.
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
        $email = Str::lower(trim((string) $this->argument('email')));

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

        app(UserAccountService::class)->create([
            'name' => (string) ($this->option('name') ?: Str::before($email, '@')),
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'departments' => $departments,
            'job_title' => $this->option('job-title'),
            'phone' => $this->option('phone'),
        ]);

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
