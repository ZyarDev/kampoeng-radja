<?php

namespace Database\Seeders;

use App\Models\Penempatan;
use Illuminate\Database\Seeder;

class PenempatanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (
            [
                'Design',
                'Sosmed',
                'IT',
                'Mark 1',
                'Mark 2',
                'OPS 1',
                'OPS 2',
                'Teknisi',
                'Driver',
                'Security',
                'Admin',
                'Tukang',
                'Kebersihan',
                'JWP',
                'Resto',
                'Front Gate / FG',
                'Galery',
                'Outbound',
            ] as $placementName
        ) {
            Penempatan::firstOrCreate(['nama_penempatan' => $placementName]);
        }
    }
}
