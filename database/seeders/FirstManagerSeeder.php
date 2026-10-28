<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first company manager so a fresh install can be used at once.
 * The account is only created when the database has no manager yet, so
 * re-running the seeder never resets an existing password.
 */
class FirstManagerSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('role', Role::Manager->value)->exists()) {
            $this->command?->info('يوجد حساب مدير بالفعل؛ لم يتم إنشاء حساب جديد.');

            return;
        }

        $email = config('crm.first_manager.email') ?: 'manager@cible.test';
        $password = config('crm.first_manager.password');
        $generated = false;

        if (blank($password)) {
            $password = Str::password(16, symbols: false);
            $generated = true;
        }

        User::query()->create([
            'name' => config('crm.first_manager.name'),
            'email' => $email,
            'password' => $password,
            'role' => Role::Manager,
            'is_active' => true,
        ]);

        $this->command?->info("تم إنشاء حساب مدير الشركة: {$email}");

        if ($generated) {
            $this->command?->warn("كلمة المرور المؤقتة: {$password} — غيّرها بعد أول دخول.");
        }
    }
}
