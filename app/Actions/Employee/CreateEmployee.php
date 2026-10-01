<?php

namespace App\Actions\Employee;

use App\Models\Karyawan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreateEmployee
{
    public function handle(array $data, ?UploadedFile $photo, ?UploadedFile $signature): Karyawan
    {
        $path = $photo?->store('employee-ktp', 'local');
        $signaturePath = $signature?->store('karyawan/tanda-tangan', 'local');

        try {
            return DB::transaction(function () use ($data, $path, $signaturePath): Karyawan {
                unset($data['foto_ktp'], $data['foto_tanda_tangan']);

                return Karyawan::create([
                    ...$data,
                    'foto_ktp' => $path ?: null,
                    'foto_tanda_tangan' => $signaturePath ?: null,
                ]);
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            if ($signaturePath) {
                Storage::disk('local')->delete($signaturePath);
            }

            throw $exception;
        }
    }
}
