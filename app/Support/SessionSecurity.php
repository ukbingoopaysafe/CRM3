<?php

namespace App\Support;

/**
 * D-074 asks for a session cookie that carries the Secure flag in production
 * without forcing HTTPS-only cookies on the local development machine. An
 * explicit SESSION_SECURE_COOKIE always wins, so a deployment can opt out.
 */
final class SessionSecurity
{
    public static function cookieIsSecure(bool $isProduction): bool
    {
        $explicit = env('SESSION_SECURE_COOKIE');

        return $explicit === null
            ? $isProduction
            : filter_var($explicit, FILTER_VALIDATE_BOOL);
    }
}
