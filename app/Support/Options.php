<?php

namespace App\Support;

/**
 * Reference lists approved in WORKFLOWS §2.7. These are selectable values,
 * not entities: they may be reworded later without changing record meaning.
 */
final class Options
{
    /**
     * @return array<string, string>
     */
    public static function unitTypes(): array
    {
        return [
            'apartment' => 'شقة',
            'studio' => 'استوديو',
            'villa' => 'فيلا',
            'other' => 'أخرى',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function finishing(): array
    {
        return [
            'unfinished' => 'بدون تشطيب',
            'semi_finished' => 'نصف تشطيب',
            'super_lux' => 'سوبر لوكس',
            'ultra_super_lux' => 'ألترا سوبر لوكس',
            'hotel' => 'فندقي',
            'smart' => 'ذكي',
            'other' => 'أخرى',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function floorPreference(): array
    {
        return [
            'ground' => 'أرضي',
            'low' => 'منخفض',
            'middle' => 'متوسط',
            'high' => 'مرتفع',
            'last' => 'أخير',
            'no_preference' => 'لا تفضيل',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function elevator(): array
    {
        return [
            'required' => 'مطلوب',
            'not_required' => 'غير مطلوب',
            'no_preference' => 'لا تفضيل',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function family(): array
    {
        return [
            'yes' => 'أسرة',
            'no' => 'أفراد',
        ];
    }

    public static function label(array $options, ?string $value): ?string
    {
        return $value === null ? null : ($options[$value] ?? $value);
    }
}
