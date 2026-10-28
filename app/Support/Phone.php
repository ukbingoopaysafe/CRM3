<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use libphonenumber\PhoneNumberUtil;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Phone handling approved in D-029: Egypt is the default country, other
 * countries are selectable, numbers are stored as E.164 and invalid input
 * fails closed instead of being stored as-is.
 */
final class Phone
{
    public const DEFAULT_COUNTRY = 'EG';

    /**
     * @throws ValidationException
     */
    public static function normalize(string $raw, ?string $country = null): string
    {
        $country = strtoupper($country ?: self::DEFAULT_COUNTRY);

        try {
            return (string) (new PhoneNumber(trim($raw), $country))->formatE164();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف غير صالح. أدخل رقمًا صحيحًا مع رمز الدولة.',
            ]);
        }
    }

    public static function isValid(string $raw, ?string $country = null): bool
    {
        try {
            self::normalize($raw, $country);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Detect an international prefix that contradicts the selected country,
     * e.g. a pasted "+966..." number while Egypt is selected. Returns the
     * detected ISO country code, or null when there is no conflict.
     */
    public static function detectCountryConflict(string $raw, ?string $country = null): ?string
    {
        $raw = trim($raw);

        if (! str_starts_with($raw, '+')) {
            return null;
        }

        $expected = strtoupper($country ?: self::DEFAULT_COUNTRY);

        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = $util->parse($raw, 'ZZ');
            $detected = $util->getRegionCodeForCountryCode($parsed->getCountryCode());
        } catch (\Throwable) {
            return null;
        }

        if ($detected === '' || $detected === 'ZZ' || $detected === $expected) {
            return null;
        }

        return $detected;
    }

    /**
     * E.164 check used by the WhatsApp link and by stored-data guards (D-053).
     */
    public static function isE164(?string $value): bool
    {
        return is_string($value) && preg_match('/^\+[1-9]\d{7,15}$/', $value) === 1;
    }
}
