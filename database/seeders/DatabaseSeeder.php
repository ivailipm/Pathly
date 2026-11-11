<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the demo library (log in as test@example.com / password).
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
