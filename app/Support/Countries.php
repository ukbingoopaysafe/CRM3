<?php

namespace App\Support;

/**
 * Phone countries selectable next to every phone field (D-029). Egypt is the
 * default; the list covers the markets this CRM actually serves.
 */
final class Countries
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'EG' => 'مصر',
            'SA' => 'السعودية',
            'AE' => 'الإمارات',
            'KW' => 'الكويت',
            'QA' => 'قطر',
            'BH' => 'البحرين',
            'OM' => 'عُمان',
            'JO' => 'الأردن',
            'LB' => 'لبنان',
            'IQ' => 'العراق',
            'SY' => 'سوريا',
            'PS' => 'فلسطين',
            'SD' => 'السودان',
            'LY' => 'ليبيا',
            'TN' => 'تونس',
            'DZ' => 'الجزائر',
            'MA' => 'المغرب',
            'TR' => 'تركيا',
            'GB' => 'المملكة المتحدة',
            'US' => 'الولايات المتحدة',
        ];
    }
}
