<?php

namespace Database\Seeders;

use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Penempatan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    /**
     * Seed optional development data. This seeder is never called by DatabaseSeeder.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DepartemenSeeder::class,
            JabatanSeeder::class,
            PenempatanSeeder::class,
        ]);

        $departemen = Departemen::query()->where('nama_departemen', 'Management')->firstOrFail();
        $jabatan = Jabatan::query()->where('nama_jabatan', 'IT')->firstOrFail();
        $penempatan = Penempatan::query()->where('nama_penempatan', 'IT')->firstOrFail();
        $superAdminRole = Role::query()->where('nama_role', 'super_admin')->firstOrFail();

        $karyawan = Karyawan::updateOrCreate(
            ['nik' => 'ADMIN001'],
            [
                'nama' => 'Admin Sistem',
                'tanggal_lahir' => '2000-01-01',
                'tempat_lahir' => 'Jambi',
                'jenis_kelamin' => 'L',
                'alamat' => 'Kampoeng Radja',
                'agama' => 'islam',
                'status_perkawinan' => 'belum kawin',
                'pendidikan' => 'S1',
                'jabatan_id' => $jabatan->id,
                'departemen_id' => $departemen->id,
                'penempatan_id' => $penempatan->id,
                'status_keaktifan' => 'aktif',
                'status_kerja' => 'kontrak',
                'tanggal_masuk' => '2026-01-01',
                'tanggal_keluar' => null,
                'no_hp' => '080000000000',
                'foto_ktp' => null,
            ],
        );

        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'karyawan_id' => $karyawan->id,
                'role_id' => $superAdminRole->id,
                'pin' => '123456',
                'is_active' => true,
                'must_change_pin' => false,
            ],
        );

        foreach (range(1, 20) as $number) {
            $attributes = Karyawan::factory()
                ->state(['nik' => sprintf('DUMMY%03d', $number)])
                ->make()
                ->getAttributes();

            Karyawan::updateOrCreate(['nik' => $attributes['nik']], $attributes);
        }
    }
}
