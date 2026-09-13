<?php

namespace Database\Factories;

use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Penempatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Karyawan> */
class KaryawanFactory extends Factory
{
    protected $model = Karyawan::class;

    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify('DUMMY########'),
            'nama' => fake()->name(),
            'tanggal_lahir' => fake()->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'tempat_lahir' => fake()->city(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'alamat' => fake()->address(),
            'agama' => fake()->randomElement(['islam', 'kristen', 'katolik', 'hindu', 'buddha', 'konghucu']),
            'status_perkawinan' => fake()->randomElement(['belum kawin', 'kawin', 'cerai hidup', 'cerai mati']),
            'pendidikan' => fake()->randomElement(['SMA', 'SMK', 'D3', 'D4', 'S1', 'S2']),
            'jabatan_id' => $this->masterId(Jabatan::class, 'JabatanSeeder'),
            'departemen_id' => $this->masterId(Departemen::class, 'DepartemenSeeder'),
            'penempatan_id' => $this->masterId(Penempatan::class, 'PenempatanSeeder'),
            'atasan_langsung_id' => null,
            'status_keaktifan' => 'aktif',
            'status_kerja' => fake()->randomElement(['kontrak', 'magang', 'buruh', 'freelance']),
            'tanggal_masuk' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'tanggal_keluar' => null,
            'no_hp' => fake()->numerify('08##########'),
            'foto_ktp' => null,
            'foto_tanda_tangan' => null,
        ];
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function masterId(string $model, string $seeder): int
    {
        $id = $model::query()->inRandomOrder()->value('id');

        if (! $id) {
            throw new \LogicException("Master {$model} belum tersedia. Jalankan {$seeder} terlebih dahulu.");
        }

        return $id;
    }
}
