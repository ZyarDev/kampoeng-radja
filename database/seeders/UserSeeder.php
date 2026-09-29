<?php

namespace Database\Seeders;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $karyawans = Karyawan::query()
            ->with('jabatan')
            ->orderBy('id')
            ->get();

        foreach ($karyawans as $karyawan) {
            if (! $karyawan->jabatan) {
                throw new RuntimeException(
                    "Karyawan {$karyawan->nama} ({$karyawan->nik}) tidak memiliki jabatan."
                );
            }

            if (! $karyawan->jabatan->role_id) {
                throw new RuntimeException(
                    "Jabatan {$karyawan->jabatan->nama_jabatan} belum memiliki role."
                );
            }

            $existingUser = User::query()
                ->where('karyawan_id', $karyawan->id)
                ->first();

            if ($existingUser) {
                // Jangan ubah username existing hanya karena nama karyawan berubah.
                $existingUser->update([
                    'role_id' => $karyawan->jabatan->role_id,
                    'is_active' => true,
                ]);

                continue;
            }

            $username = $this->generateUniqueUsername(
                $karyawan->nama
            );

            User::create([
                'karyawan_id' => $karyawan->id,
                'role_id' => $karyawan->jabatan->role_id,
                'username' => $username,
                'pin' => '123456',
                'is_active' => true,
                'must_change_pin' => true,
            ]);
        }
    }

    private function generateUniqueUsername(string $nama): string
    {
        $parts = collect(
            preg_split('/\s+/', trim($nama))
        )
            ->map(function (string $part): string {
                return Str::lower(
                    preg_replace('/[^a-zA-Z0-9]/', '', $part)
                );
            })
            ->filter()
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Lewati inisial satu huruf
        |--------------------------------------------------------------------------
        |
        | Contoh:
        | M. Zidane Auliando -> zidane
        | M Daut            -> daut
        |
        */

        while (
            $parts->count() > 1 &&
            strlen($parts->first()) === 1
        ) {
            $parts->shift();
            $parts = $parts->values();
        }

        if ($parts->isEmpty()) {
            throw new RuntimeException(
                "Tidak dapat membuat username dari nama: {$nama}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Kandidat pertama
        |--------------------------------------------------------------------------
        */

        $baseUsername = $parts->first();

        if (! User::query()->where('username', $baseUsername)->exists()) {
            return $baseUsername;
        }

        /*
        |--------------------------------------------------------------------------
        | Jika nama pertama bentrok, tambahkan nama berikutnya
        |--------------------------------------------------------------------------
        |
        | Andi Saputra -> andi
        | Andi Pratama -> andi.pratama
        |
        */

        if ($parts->count() > 1) {
            for ($i = 2; $i <= $parts->count(); $i++) {
                $candidate = $parts
                    ->take($i)
                    ->implode('.');

                if (
                    ! User::query()
                        ->where('username', $candidate)
                        ->exists()
                ) {
                    return $candidate;
                }
            }

            $baseUsername = $parts->implode('.');
        }

        /*
        |--------------------------------------------------------------------------
        | Jika masih bentrok, tambahkan angka
        |--------------------------------------------------------------------------
        */

        $number = 2;

        while (true) {
            $candidate = $baseUsername . $number;

            if (
                ! User::query()
                    ->where('username', $candidate)
                    ->exists()
            ) {
                return $candidate;
            }

            $number++;
        }
    }
}