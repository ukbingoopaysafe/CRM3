<?php

namespace App\Support;

/**
 * Reference names (areas, lead sources, close reasons) are compared after a
 * light Arabic-aware normalization so that spacing and letter variants do not
 * create hidden duplicates (D-032, D-042).
 */
final class ArabicText
{
    private const DIACRITICS = '/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u';

    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace(self::DIACRITICS, '', $value) ?? $value;
        $value = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $value);
        $value = str_replace(['ى'], 'ي', $value);
        $value = str_replace(['ؤ'], 'و', $value);
        $value = str_replace(['ئ'], 'ي', $value);
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower($value);
    }

    /**
     * Similarity in the 0..1 range, used to warn about possible duplicate
     * customers by name only; a warning never blocks creation (D-028).
     */
    public static function similarity(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
