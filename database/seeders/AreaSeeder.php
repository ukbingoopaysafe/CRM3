<?php

namespace Database\Seeders;

use App\Enums\Governorate;
use App\Models\Area;
use Illuminate\Database\Seeder;

/**
 * The operational area list approved in D-042, exactly as approved: no extra
 * neighbourhoods, and "الجيزة" is an area inside the Giza governorate, not a
 * replacement for the governorate field (D-041).
 */
class AreaSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private const AREAS = [
        'cairo' => [
            'التجمع الخامس',
            'الزمالك',
            'المعادي',
            'مدينة نصر',
            'مدينتي',
        ],
        'giza' => [
            'الجيزة',
            '6 أكتوبر',
            'حدائق أكتوبر',
            'الشيخ زايد',
            'حدائق الأهرام',
            'الهرم',
            'فيصل',
            'الدقي',
            'العجوزة',
            'المهندسين',
        ],
    ];

    public function run(): void
    {
        foreach (self::AREAS as $governorate => $names) {
            foreach ($names as $name) {
                Area::firstOrCreate(
                    [
                        'name' => $name,
                        'governorate' => Governorate::from($governorate),
                    ],
                    ['is_active' => true],
                );
            }
        }
    }
}
