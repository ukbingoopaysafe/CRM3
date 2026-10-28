<?php

namespace Database\Seeders;

use App\Models\Rental\LeadSource as RentalLeadSource;
use App\Models\Resale\LeadSource as ResaleLeadSource;
use Illuminate\Database\Seeder;

/**
 * The initial lead-source values approved in D-051, seeded separately inside
 * each department. "أخرى" always requires the employee to write a description.
 */
class LeadSourceSeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: bool}>
     */
    private const VALUES = [
        ['مكالمة هاتفية', false],
        ['زيارة مباشرة', false],
        ['إحالة من موظف', false],
        ['إحالة من عميل أو معرفة', false],
        ['وسائل التواصل الاجتماعي', false],
        ['إعلان إلكتروني', false],
        ['الموقع الإلكتروني', false],
        ['واتساب', false],
        ['أخرى', true],
    ];

    public function run(): void
    {
        foreach ([RentalLeadSource::class, ResaleLeadSource::class] as $model) {
            foreach (self::VALUES as $index => [$name, $requiresDescription]) {
                $model::firstOrCreate(
                    ['name' => $name],
                    [
                        'requires_description' => $requiresDescription,
                        'is_active' => true,
                        'sort_order' => $index + 1,
                    ],
                );
            }
        }
    }
}
