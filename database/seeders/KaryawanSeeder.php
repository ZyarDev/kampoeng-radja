<?php

namespace Database\Seeders;

use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Penempatan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KaryawanSeeder extends Seeder
{
    public function run(): void
    {
        $datasetPath = database_path('seeders/data/karyawan.php');

        if (! is_file($datasetPath)) {
            throw new RuntimeException(
                "Dataset karyawan asli belum tersedia. Salin database/seeders/data/karyawan.example.php " .
                "menjadi database/seeders/data/karyawan.php lalu isi datanya."
            );
        }

        $records = $this->filterPopulatedRecords(require $datasetPath);
        $this->validateDataset($records);

        $jabatan = Jabatan::query()->pluck('id', 'nama_jabatan');
        $departemen = Departemen::query()->pluck('id', 'nama_departemen');
        $penempatan = Penempatan::query()->pluck('id', 'nama_penempatan');

        DB::transaction(function () use ($records, $jabatan, $departemen, $penempatan): void {
            foreach ($records as $record) {
                Karyawan::updateOrCreate(
                    ['nik' => $record['nik']],
                    [
                        'nama' => $record['nama'],
                        'tanggal_lahir' => $record['tanggal_lahir'],
                        'tempat_lahir' => $record['tempat_lahir'],
                        'jenis_kelamin' => $record['jenis_kelamin'],
                        'alamat' => $record['alamat'],
                        'agama' => $record['agama'],
                        'status_perkawinan' => $record['status_perkawinan'],
                        'pendidikan' => $record['pendidikan'],
                        'jabatan_id' => $jabatan[$record['jabatan']],
                        'departemen_id' => $departemen[$record['departemen']],
                        'penempatan_id' => $penempatan[$record['penempatan']],
                        'atasan_langsung_id' => null,
                        'status_keaktifan' => $record['status_keaktifan'],
                        'status_kerja' => $record['status_kerja'],
                        'tanggal_masuk' => $record['tanggal_masuk'],
                        'tanggal_keluar' => $record['tanggal_keluar'],
                        'no_hp' => $record['no_hp'],
                        'foto_ktp' => $record['foto_ktp'],
                        'foto_tanda_tangan' => $record['foto_tanda_tangan'],
                    ],
                );
            }

            $employeesByNik = Karyawan::query()
                ->whereIn('nik', array_column($records, 'nik'))
                ->get()
                ->keyBy('nik');

            foreach ($records as $record) {
                $managerNik = $record['atasan_langsung_nik'];
                $managerId = $managerNik === null ? null : $employeesByNik[$managerNik]->id;

                $employeesByNik[$record['nik']]->update([
                    'atasan_langsung_id' => $managerId,
                ]);
            }
        });
    }

    private function validateDataset(mixed $records): void
    {
        if (! is_array($records) || count($records) === 0) {
            throw new RuntimeException('Dataset karyawan tidak memiliki record yang terisi.');
        }

        $requiredFields = [
            'nama', 'nik', 'tanggal_lahir', 'tempat_lahir', 'jenis_kelamin',
            'alamat', 'agama', 'status_perkawinan', 'pendidikan', 'jabatan',
            'departemen', 'penempatan', 'status_keaktifan', 'status_kerja',
            'tanggal_masuk', 'tanggal_keluar', 'no_hp', 'foto_ktp',
            'foto_tanda_tangan', 'atasan_langsung_nik',
        ];
        $niks = [];

        foreach ($records as $index => $record) {
            $label = 'Record karyawan ke-' . ($index + 1);

            if (! is_array($record)) {
                throw new RuntimeException("{$label} harus berupa array.");
            }

            foreach ($requiredFields as $field) {
                if (! array_key_exists($field, $record)) {
                    throw new RuntimeException("{$label} tidak memiliki field {$field}.");
                }
            }

            foreach (['nama', 'nik', 'tempat_lahir', 'jenis_kelamin', 'alamat', 'agama', 'status_perkawinan', 'pendidikan', 'jabatan', 'departemen', 'penempatan', 'status_keaktifan', 'status_kerja', 'tanggal_masuk', 'no_hp'] as $field) {
                if ($record[$field] === null || trim((string) $record[$field]) === '') {
                    throw new RuntimeException("{$label}: field {$field} wajib diisi.");
                }
            }

            if (isset($niks[$record['nik']])) {
                throw new RuntimeException('NIK ' . $record['nik'] . ' duplikat pada record ' . ($index + 1) . '.');
            }

            $niks[$record['nik']] = true;
        }

        foreach ($records as $index => $record) {
            $managerNik = $record['atasan_langsung_nik'];

            if ($managerNik !== null && ! isset($niks[$managerNik])) {
                throw new RuntimeException(
                    'Atasan langsung dengan NIK ' . $managerNik .
                    ' tidak ditemukan untuk record ' . ($index + 1) . '.'
                );
            }

            if ($managerNik === $record['nik']) {
                throw new RuntimeException(
                    'Karyawan dengan NIK ' . $record['nik'] . ' tidak boleh menjadi atasan dirinya sendiri.'
                );
            }
        }

        foreach ($records as $index => $record) {
            $this->masterExists(Jabatan::class, 'nama_jabatan', $record['jabatan'], 'jabatan', $index);
            $this->masterExists(Departemen::class, 'nama_departemen', $record['departemen'], 'departemen', $index);
            $this->masterExists(Penempatan::class, 'nama_penempatan', $record['penempatan'], 'penempatan', $index);
        }
    }

    private function filterPopulatedRecords(mixed $records): array
    {
        if (! is_array($records)) {
            return [];
        }

        $dataFields = [
            'nama', 'nik', 'tanggal_lahir', 'tempat_lahir', 'jenis_kelamin',
            'alamat', 'agama', 'status_perkawinan', 'pendidikan', 'jabatan',
            'departemen', 'penempatan', 'status_kerja', 'tanggal_masuk', 'no_hp',
        ];

        return array_values(array_filter($records, function (mixed $record) use ($dataFields): bool {
            if (! is_array($record)) {
                return true;
            }

            foreach ($dataFields as $field) {
                if (isset($record[$field]) && trim((string) $record[$field]) !== '') {
                    return true;
                }
            }

            return ($record['status_keaktifan'] ?? 'aktif') !== 'aktif';
        }));
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function masterExists(string $model, string $column, string $value, string $label, int $index): void
    {
        if (! $model::query()->where($column, $value)->exists()) {
            throw new RuntimeException(
                "Master {$label} '{$value}' tidak ditemukan untuk record " . ($index + 1) . '.'
            );
        }
    }
}
