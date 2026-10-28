<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Console\Command;

/**
 * The console half of the MFA switch (D-080). It exists for the case the panel
 * cannot cover: the manager with the lost phone is the only one who could sign
 * in. Everything it writes goes through UserAccountService, so the audit trail
 * matches the team screen.
 */
class DisableMultiFactorAuthentication extends Command
{
    protected $signature = 'users:disable-mfa
        {email : البريد الإلكتروني للحساب}';

    protected $description = 'تعطيل المصادقة الثنائية عن حساب';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('لا يوجد حساب بهذا البريد الإلكتروني.');

            return self::FAILURE;
        }

        if (! $user->hasMultiFactorAuthentication()) {
            $this->info('المصادقة الثنائية غير مفعّلة على هذا الحساب أصلًا.');

            return self::SUCCESS;
        }

        app(UserAccountService::class)->disableMfa($user);

        $this->info("تم تعطيل المصادقة الثنائية عن: {$email}");

        return self::SUCCESS;
    }
}
