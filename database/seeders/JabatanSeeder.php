<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use Illuminate\Database\Seeder;

class JabatanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Dirut', 'Direktur', 'Manajer', 'SPV', 'Marketing', 'Marcom', 'IT', 'Finance', 'Kasir', 'Operasional', 'General', 'Facility'] as $name) Jabatan::firstOrCreate(['nama_jabatan' => $name]);
    }
}
