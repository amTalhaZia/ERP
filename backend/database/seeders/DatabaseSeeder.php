<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production-safe seeder: only the permission catalogue.
 * Demo data lives in DemoSeeder and must be invoked explicitly:
 *   php artisan db:seed --class=DemoSeeder   (refused in production)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
    }
}
