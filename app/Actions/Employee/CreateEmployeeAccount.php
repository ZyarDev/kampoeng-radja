<?php

namespace App\Actions\Employee;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEmployeeAccount
{
    /** @param array{username:string,pin:string} $data */
    public function handle(Karyawan $employee, array $data): User
    {
        return DB::transaction(function () use ($employee, $data): User {
            $lockedEmployee = Karyawan::query()
                ->with('jabatan:id,nama_jabatan,role_id')
                ->with('jabatan.role:id,nama_role')
                ->lockForUpdate()
                ->findOrFail($employee->id);

            if ($lockedEmployee->user()->exists()) {
                throw ValidationException::withMessages([
                    'account' => 'Karyawan ini sudah memiliki akun.',
                ]);
            }

            $jabatan = $lockedEmployee->jabatan;
            if (! $jabatan?->role_id) {
                throw ValidationException::withMessages([
                    'account' => sprintf(
                        'Role untuk jabatan "%s" belum ditentukan. Silakan tentukan Role pada Master Organisasi → Data Jabatan terlebih dahulu.',
                        $jabatan?->nama_jabatan ?? '-',
                    ),
                ]);
            }

            return User::create([
                'karyawan_id' => $lockedEmployee->id,
                'role_id' => $jabatan->role_id,
                'username' => $data['username'],
                'pin' => $data['pin'],
                'is_active' => $lockedEmployee->status_keaktifan === 'aktif',
                'must_change_pin' => true,
            ]);
        });
    }
}
