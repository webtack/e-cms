<?php

namespace Webtack\Ecms\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the initial administrator.
     *
     * Credentials come only from ADMIN_EMAIL / ADMIN_PASSWORD (no defaults in any environment); see {@see AdminSeeder}.
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);
    }
}
