<?php

namespace Tests\Feature;

use App\Support\SessionSecurity;
use Tests\TestCase;

/**
 * D-065 and D-074 are browser-level rules, so the checks run against a real
 * response and against the one function that decides the cookie flag.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_the_panel_sends_the_basic_security_headers(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->assertHeader(
            'Content-Security-Policy',
            "base-uri 'self'; object-src 'none'; frame-ancestors 'self'",
        );
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_only_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get('/admin/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_sessions_are_encrypted_without_an_explicit_setting(): void
    {
        $this->assertTrue(config('session.encrypt'));
    }

    public function test_the_session_cookie_is_secure_in_production_unless_overridden(): void
    {
        $this->setSecureCookieEnv(null);
        $this->assertFalse(SessionSecurity::cookieIsSecure(isProduction: false));
        $this->assertTrue(SessionSecurity::cookieIsSecure(isProduction: true));

        $this->setSecureCookieEnv('false');
        $this->assertFalse(SessionSecurity::cookieIsSecure(isProduction: true));

        $this->setSecureCookieEnv('true');
        $this->assertTrue(SessionSecurity::cookieIsSecure(isProduction: false));

        $this->setSecureCookieEnv(null);
    }

    private function setSecureCookieEnv(?string $value): void
    {
        if ($value === null) {
            unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);
            putenv('SESSION_SECURE_COOKIE');

            return;
        }

        $_ENV['SESSION_SECURE_COOKIE'] = $value;
        $_SERVER['SESSION_SECURE_COOKIE'] = $value;
        putenv("SESSION_SECURE_COOKIE={$value}");
    }
}
