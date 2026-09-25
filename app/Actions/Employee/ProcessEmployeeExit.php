<?php

namespace App\Actions\Employee;

use App\Models\Karyawan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessEmployeeExit
{
    public function handle(Karyawan $employee, string $exitDate): void
    {
        DB::transaction(function () use ($employee, $exitDate): void {
            $lockedEmployee = Karyawan::query()->lockForUpdate()->findOrFail($employee->id);

            if ($lockedEmployee->status_keaktifan === 'nonaktif') {
                throw ValidationException::withMessages([
                    'tanggal_keluar' => 'Karyawan ini sudah berstatus nonaktif.',
                ]);
            }

            $lockedEmployee->update([
                'tanggal_keluar' => $exitDate,
                'status_keaktifan' => 'nonaktif',
            ]);

            $account = $lockedEmployee->user()->lockForUpdate()->first();
            $account?->update(['is_active' => false]);
        });
    }
}
