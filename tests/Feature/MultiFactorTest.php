<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Resources\Team\Pages\ListUsers;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

/**
 * TOTP is available but never forced (D-079), and the manager keeps the switch
 * to turn it off for an account that lost its phone (D-080).
 */
class MultiFactorTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_login_asks_for_the_password_only_when_mfa_is_off(): void
    {
        $employee = $this->employee(Department::Rental);

        Livewire::test(Login::class)
            ->fillForm(['email' => $employee->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($employee);
    }

    public function test_an_enabled_account_must_answer_the_code_challenge(): void
    {
        $employee = $this->enableMfa($this->employee(Department::Rental));

        Livewire::test(Login::class)
            ->fillForm(['email' => $employee->email, 'password' => 'password'])
            ->call('authenticate');

        $this->assertGuest();
    }

    public function test_the_correct_code_completes_the_login(): void
    {
        $employee = $this->enableMfa($this->employee(Department::Rental));
        $code = app(AppAuthentication::class)->getCurrentCode($employee);

        Livewire::test(Login::class)
            ->fillForm(['email' => $employee->email, 'password' => 'password'])
            ->call('authenticate')
            ->set('data.multiFactor.app.code', $code)
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($employee);
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $employee = $this->enableMfa($this->employee(Department::Rental));

        Livewire::test(Login::class)
            ->fillForm(['email' => $employee->email, 'password' => 'password'])
            ->call('authenticate')
            ->set('data.multiFactor.app.code', '000000')
            ->call('authenticate')
            ->assertHasErrors();

        $this->assertGuest();
    }

    public function test_the_team_screen_shows_who_uses_mfa_and_can_turn_it_off(): void
    {
        $employee = $this->enableMfa($this->employee(Department::Rental));
        $plain = $this->employee(Department::Resale);

        $this->actingAs($this->manager());

        Livewire::test(ListUsers::class)
            ->assertTableColumnStateSet('multi_factor', 'مفعّلة', $employee)
            ->assertTableColumnStateSet('multi_factor', 'معطّلة', $plain)
            ->assertActionHidden(TestAction::make('disable_mfa')->table($plain))
            ->callAction(TestAction::make('disable_mfa')->table($employee))
            ->assertHasNoActionErrors();

        $employee->refresh();

        $this->assertFalse($employee->hasMultiFactorAuthentication());
        $this->assertNull($employee->getAppAuthenticationRecoveryCodes());
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user_mfa_disabled',
            'auditable_id' => $employee->getKey(),
        ]);
    }

    public function test_only_the_manager_may_turn_mfa_off(): void
    {
        $employee = $this->enableMfa($this->employee(Department::Rental));

        $this->expectException(ValidationException::class);

        app(UserAccountService::class)->disableMfa($employee, $this->employee(Department::Resale));
    }

    public function test_the_console_can_turn_mfa_off_when_the_panel_is_unreachable(): void
    {
        $employee = $this->enableMfa($this->employee(Department::Rental));

        $this->artisan('users:disable-mfa', ['email' => $employee->email])
            ->assertSuccessful();

        $this->assertFalse($employee->fresh()->hasMultiFactorAuthentication());
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user_mfa_disabled',
            'auditable_id' => $employee->getKey(),
        ]);
    }

    public function test_the_account_holder_opts_in_from_the_profile_page(): void
    {
        $employee = $this->employee(Department::Rental);

        $this->actingAs($employee)
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee(__('filament-panels::auth/multi-factor/app/provider.management_schema.actions.label'));

        $this->assertFalse($employee->hasMultiFactorAuthentication());
    }

    private function enableMfa(User $user): User
    {
        $user->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());
        $user->saveAppAuthenticationRecoveryCodes(['hashed-recovery-code']);

        return $user;
    }
}
