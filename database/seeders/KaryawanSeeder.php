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
                'Dataset karyawan belum tersedia di database/seeders/data/karyawan.php.'
            );
        }

        $records = $this->filterPopulatedRecords(require $datasetPath);
        $this->validateDataset($records);

        $jabatan = Jabatan::query()->pluck('id', 'nama_jabatan');
        $departemen = Departemen::query()->pluck('id', 'nama_departemen');
        $penempatan = Penempatan::query()->pluck('id', 'nama_penempatan');

        DB::transaction(function () use (
            $records,
            $jabatan,
            $departemen,
            $penempatan
        ): void {
            // PASS 1: buat/update seluruh karyawan tanpa menimpa supervisor/assets web.
            foreach ($records as $record) {
                Karyawan::updateOrCreate(
                    ['nik' => (string) $record['nik']],
                    [
                        'nama' => $record['nama'],
                        'tanggal_lahir' => $record['tanggal_lahir'] ?? null,
                        'tempat_lahir' => $record['tempat_lahir'] ?? null,
                        'jenis_kelamin' => $record['jenis_kelamin'] ?? null,
                        'alamat' => $record['alamat'] ?? null,
                        'agama' => $this->normalizeAgama($record['agama'] ?? null),
                        'status_perkawinan' => $record['status_perkawinan'] ?? null,
                        'pendidikan' => $record['pendidikan'] ?? null,
                        'jabatan_id' => $jabatan[$record['jabatan']],
                        'departemen_id' => filled($record['departemen'] ?? null)
                            ? $departemen[$record['departemen']]
                            : null,
                        'penempatan_id' => filled($record['penempatan'] ?? null)
                            ? $penempatan[$record['penempatan']]
                            : null,
                        'status_keaktifan' => $record['status_keaktifan'] ?? 'aktif',
                        'status_kerja' => $record['status_kerja'] ?? null,
                        'tanggal_masuk' => $record['tanggal_masuk'] ?? null,
                        'tanggal_keluar' => $record['tanggal_keluar'] ?? null,
                        'no_hp' => $record['no_hp'] ?? null,
                    ]
                );
            }

            // PASS 2: resolve helper NIK atasan -> karyawan.atasan_langsung_id.
            $employeesByNik = Karyawan::query()
                ->whereIn(
                    'nik',
                    array_map(
                        static fn ($nik): string => (string) $nik,
                        array_column($records, 'nik')
                    )
                )
                ->get()
                ->keyBy(
                    static fn (Karyawan $employee): string => (string) $employee->nik
                );

            foreach ($records as $record) {
                $managerNik = $record['atasan_langsung_nik'] ?? null;

                if ($managerNik === null || trim((string) $managerNik) === '') {
                    continue;
                }

                $employeeNik = (string) $record['nik'];
                $managerNik = (string) $managerNik;

                $employee = $employeesByNik[$employeeNik];
                $manager = $employeesByNik[$managerNik];

                // Fresh seed akan terisi, rerun tidak menimpa perubahan web.
                if ($employee->atasan_langsung_id === null) {
                    $employee->update([
                        'atasan_langsung_id' => $manager->id,
                    ]);
                }
            }
        });
    }

    private function validateDataset(mixed $records): void
    {
        if (! is_array($records) || count($records) === 0) {
            throw new RuntimeException(
                'Dataset karyawan tidak memiliki record yang terisi.'
            );
        }

        $requiredKeys = [
            'nama',
            'nik',
            'tanggal_lahir',
            'tempat_lahir',
            'jenis_kelamin',
            'alamat',
            'agama',
            'status_perkawinan',
            'pendidikan',
            'jabatan',
            'departemen',
            'penempatan',
            'atasan_langsung_nik',
            'status_keaktifan',
            'status_kerja',
            'tanggal_masuk',
            'tanggal_keluar',
            'no_hp',
            'foto_ktp',
            'foto_tanda_tangan',
        ];

        $requiredValues = [
            'nama',
            'nik',
            'jabatan',
        ];

        $niks = [];

        foreach ($records as $index => $record) {
            $label = 'Record karyawan ke-' . ($index + 1);

            if (! is_array($record)) {
                throw new RuntimeException("{$label} harus berupa array.");
            }

            foreach ($requiredKeys as $field) {
                if (! array_key_exists($field, $record)) {
                    throw new RuntimeException(
                        "{$label} tidak memiliki field {$field}."
                    );
                }
            }

            foreach ($requiredValues as $field) {
                if (
                    $record[$field] === null ||
                    trim((string) $record[$field]) === ''
                ) {
                    throw new RuntimeException(
                        "{$label}: field {$field} wajib diisi."
                    );
                }
            }

            $nik = (string) $record['nik'];

            if (isset($niks[$nik])) {
                throw new RuntimeException(
                    "NIK {$nik} duplikat pada record " . ($index + 1) . '.'
                );
            }

            $niks[$nik] = true;
        }

        foreach ($records as $index => $record) {
            $managerNik = $record['atasan_langsung_nik'] ?? null;

            if ($managerNik === null || trim((string) $managerNik) === '') {
                continue;
            }

            $employeeNik = (string) $record['nik'];
            $managerNik = (string) $managerNik;

            if (! isset($niks[$managerNik])) {
                throw new RuntimeException(
                    "Atasan langsung dengan NIK {$managerNik} tidak ditemukan " .
                    'untuk record ' . ($index + 1) . '.'
                );
            }

            if ($managerNik === $employeeNik) {
                throw new RuntimeException(
                    "Karyawan dengan NIK {$employeeNik} tidak boleh menjadi " .
                    'atasan dirinya sendiri.'
                );
            }
        }

        foreach ($records as $index => $record) {
            $this->masterExists(
                Jabatan::class,
                'nama_jabatan',
                $record['jabatan'],
                'jabatan',
                $index
            );

            if (filled($record['departemen'] ?? null)) {
                $this->masterExists(
                    Departemen::class,
                    'nama_departemen',
                    $record['departemen'],
                    'departemen',
                    $index
                );
            }

            if (filled($record['penempatan'] ?? null)) {
                $this->masterExists(
                    Penempatan::class,
                    'nama_penempatan',
                    $record['penempatan'],
                    'penempatan',
                    $index
                );
            }
        }
    }

    private function filterPopulatedRecords(mixed $records): array
    {
        if (! is_array($records)) {
            return [];
        }

        return array_values(
            array_filter(
                $records,
                static function (mixed $record): bool {
                    if (! is_array($record)) {
                        return false;
                    }

                    return ! empty($record['nik']) || ! empty($record['nama']);
                }
            )
        );
    }

    private function normalizeAgama(mixed $agama): ?string
    {
        if ($agama === null || trim((string) $agama) === '') {
            return null;
        }

        return strtolower(trim((string) $agama));
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     */
    private function masterExists(
        string $model,
        string $column,
        string $value,
        string $label,
        int $index
    ): void {
        if (! $model::query()->where($column, $value)->exists()) {
            throw new RuntimeException(
                "Master {$label} '{$value}' tidak ditemukan untuk record " .
                ($index + 1) . '.'
            );
        }
    }
}
