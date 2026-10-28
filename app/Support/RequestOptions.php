<?php

namespace App\Support;

use App\Enums\Department;

/**
 * The initial operational option lists approved in WORKFLOWS.md §2.7. They are
 * kept here so the form, the table and the infolist read the same labels.
 */
final class RequestOptions
{
    public const UNIT_TYPES = [
        'apartment' => 'شقة',
        'studio' => 'استوديو',
        'villa' => 'فيلا',
        'other' => 'أخرى',
    ];

    public const FINISHING = [
        'none' => 'بدون تشطيب',
        'semi' => 'نصف تشطيب',
        'super_lux' => 'سوبر لوكس',
        'ultra_super_lux' => 'ألترا سوبر لوكس',
        'hotel' => 'فندقي',
        'smart' => 'ذكي',
        'other' => 'أخرى',
    ];

    public const FLOORS = [
        'ground' => 'أرضي',
        'low' => 'منخفض',
        'mid' => 'متوسط',
        'high' => 'مرتفع',
        'top' => 'أخير',
        'no_preference' => 'لا تفضيل',
    ];

    public const ELEVATOR = [
        'required' => 'مطلوب',
        'not_required' => 'غير مطلوب',
        'no_preference' => 'لا تفضيل',
    ];

    public const YES_NO = [
        'yes' => 'نعم',
        'no' => 'لا',
    ];

    /**
     * Unit types that make sense for the department; resale has no studio
     * market in the approved lists, but the value stays valid either way.
     *
     * @return array<string, string>
     */
    public static function unitTypes(Department $department): array
    {
        return self::UNIT_TYPES;
    }
}
