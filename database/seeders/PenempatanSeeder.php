<?php

namespace Database\Seeders;

use App\Models\Penempatan;
use Illuminate\Database\Seeder;

class PenempatanSeeder extends Seeder
{
    public function run(): void
    {
        $penempatan = [
            'MARCOM',
            'DESIGN',
            'IT',
            'PROMOSI',
            'GENERAL',
            'MARKETING',
            'OPERASIONAL',
            'FRONT GATE',
            'KIDDYLAND & FP',
            'JWP',
            'MOBIL GOLF',
            'KERETA API',
            'GALLERY & PAINTBALL',
            'OUTBOUND',
            'RESTO',
            'OA & MM',
            'RAINBOW SLIDE',
            'TEKNISI',
            'SEPEDA AIR',
        ];

        foreach ($penempatan as $namaPenempatan) {
            Penempatan::firstOrCreate([
                'nama_penempatan' => $namaPenempatan,
            ]);
        }
    }
}