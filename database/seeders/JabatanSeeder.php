<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\Role;
use Illuminate\Database\Seeder;

class JabatanSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'nama_role');
        $mapping = [
            'Dirut' => 'super_admin',
            'Direktur' => 'super_admin',
            'Manajer' => 'super_admin',
            'SPV' => 'admin',
            'Marketing' => 'user',
            'Marcom' => 'user',
            'IT' => 'user',
            'Finance' => 'user',
            'Kasir' => 'user',
            'Operasional' => 'user',
            'General' => 'user',
            'Facility' => 'user',
        ];

        foreach ($mapping as $name => $roleName) {
            Jabatan::firstOrCreate([
                'nama_jabatan' => $name,
            ], [
                'role_id' => $roles[$roleName] ?? null,
            ]);
        }
    }
}
