<?php

namespace App\Actions\Employee;

use App\Models\Karyawan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteEmployee
{
    public function handle(Karyawan $employee): void
    {
        if ($employee->user()->exists() || $employee->absensi()->exists()) {
            throw ValidationException::withMessages([
                'employee' => 'Tidak dapat dihapus karena data masih digunakan. Gunakan Nonaktifkan Karyawan.',
            ]);
        }

        $photo = $employee->foto_ktp;
        $signature = $employee->foto_tanda_tangan;
        DB::transaction(fn () => $employee->delete());

        if ($photo) {
            Storage::disk('local')->delete($photo);
        }
        if ($signature) {
            Storage::disk('local')->delete($signature);
        }
    }
}
