<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\Role;
use Illuminate\Database\Seeder;

class JabatanSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()
            ->pluck('id', 'nama_role');

        $mapping = [
            'KOMISARIS'      => 'super_admin',
            'DIREKTUR UTAMA' => 'super_admin',
            'DIREKTUR'       => 'super_admin',
            'MANAJER'        => 'super_admin',

            'SUPERVISOR'     => 'admin',

            'MARCOM'         => 'user',
            'MARKETING'      => 'user',
            'OPERASIONAL'    => 'user',
            'SECURITY'       => 'user',
            'DRIVER'         => 'user',
            'FACILITY'       => 'user',
            'FINANCE'        => 'user',
            'ADMIN'          => 'user',
            'KASIR'          => 'user',
        ];

        foreach ($mapping as $namaJabatan => $namaRole) {
            Jabatan::updateOrCreate(
                [
                    'nama_jabatan' => $namaJabatan,
                ],
                [
                    'role_id' => $roles[$namaRole]
                        ?? throw new \RuntimeException(
                            "Role {$namaRole} belum tersedia."
                        ),
                ]
            );
        }
    }
}