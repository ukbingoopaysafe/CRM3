<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data a fresh install needs: the approved area list (D-042), the
 * approved lead sources inside each department (D-051), and one manager
 * account. Close-reason categories are deliberately not seeded: D-050 forbids
 * a forced closed list, and the manager maintains them per department (D-073).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AreaSeeder::class,
            LeadSourceSeeder::class,
            FirstManagerSeeder::class,
        ]);
    }
}
