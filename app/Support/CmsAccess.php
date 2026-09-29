<?php

namespace App\Support;

use App\Models\User;

class CmsAccess
{
    /** @return array{canView:bool,canManage:bool} */
    public function for(?User $user): array
    {
        if (! $user || ! $user->is_active) {
            return $this->denied();
        }

        $user->loadMissing([
            'role:id,nama_role',
            'karyawan:id,nama,status_keaktifan,jabatan_id,departemen_id,penempatan_id',
            'karyawan.departemen:id,nama_departemen',
            'karyawan.penempatan:id,nama_penempatan',
        ]);

        $role = mb_strtolower(trim((string) $user->role?->nama_role));

        if ($role === 'super_admin') {
            return $this->granted();
        }

        if (! $user->karyawan || $user->karyawan->status_keaktifan !== 'aktif') {
            return $this->denied();
        }

        $department = mb_strtoupper(trim((string) $user->karyawan->departemen?->nama_departemen));
        if ($department === 'MARCOM') {
            return $this->granted();
        }

        $placement = mb_strtoupper(trim((string) $user->karyawan->penempatan?->nama_penempatan));
        if ($role === 'admin' && $placement === 'MARCOM') {
            return $this->granted();
        }

        return $this->denied();
    }

    /** @return array{canView:true,canManage:true} */
    private function granted(): array
    {
        return ['canView' => true, 'canManage' => true];
    }

    /** @return array{canView:false,canManage:false} */
    private function denied(): array
    {
        return ['canView' => false, 'canManage' => false];
    }
}
