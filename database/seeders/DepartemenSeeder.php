<?php

namespace Database\Seeders;

use App\Models\Departemen;
use Illuminate\Database\Seeder;

class DepartemenSeeder extends Seeder
{
    public function run(): void
    {
        $departemen = [
            'MANAJEMEN',
            'MARCOM',
            'MARKETING',
            'OPERASIONAL',
            'FAA',
        ];

        foreach ($departemen as $namaDepartemen) {
            Departemen::firstOrCreate([
                'nama_departemen' => $namaDepartemen,
            ]);
        }
    }
}