<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Note: model events are deliberately NOT suppressed here — the profile
     * strength and derived salary/experience columns are maintained by model
     * `saving` hooks, so seeded rows must go through them.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
