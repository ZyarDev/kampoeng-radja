<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('DevelopmentSeeder dilewati di luar environment local/testing.');

            return;
        }

        $this->call([
            RoleSeeder::class,
            DepartemenSeeder::class,
            JabatanSeeder::class,
            PenempatanSeeder::class,
            DevelopmentSeeder::class,
            ClosingEventMasterSeeder::class,
        ]);
    }
}
