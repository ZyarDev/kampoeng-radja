<?php

namespace App\Support;

use App\Models\User;

class ClosingEventAccess
{
    /** @return array<string, mixed> */
    public function for(?User $user): array
    {
        $closingEvent = $this->closingEventPermissions($user);
        $masterEvent = $this->masterEventPermissions($user);

        return [
            ...$closingEvent,
            'masterEvent' => $masterEvent,
            'canViewMaster' => $masterEvent['canView'],
            'canCreateMaster' => $masterEvent['canCreate'],
            'canUpdateMaster' => $masterEvent['canUpdate'],
            'canDeleteMaster' => $masterEvent['canDelete'],
            // Compatibility alias for existing shared consumers.
            'canManageMaster' => $masterEvent['canCreate'],
        ];
    }

    /** @return array{canView:bool,canCreate:bool,canUpdate:bool,canDelete:bool,canExport:bool} */
    public function closingEventPermissions(?User $user): array
    {
        if (! $this->isActiveUser($user)) {
            return $this->closingEventDenied();
        }

        $role = $this->roleName($user);
        if ($role === 'super_admin') {
            return $this->closingEventCrud();
        }

        if (in_array($this->departmentName($user), ['MARKETING', 'MARCOM'], true)) {
            return $this->closingEventCrud();
        }

        if ($role === 'admin' && in_array($this->placementName($user), ['MARKETING', 'MARCOM'], true)) {
            return [
                'canView' => true,
                'canCreate' => false,
                'canUpdate' => true,
                'canDelete' => false,
                'canExport' => true,
            ];
        }

        return [
            'canView' => true,
            'canCreate' => false,
            'canUpdate' => false,
            'canDelete' => false,
            'canExport' => false,
        ];
    }

    /** @return array{canView:bool,canCreate:bool,canUpdate:bool,canDelete:bool} */
    public function masterEventPermissions(?User $user): array
    {
        if (! $this->isActiveUser($user)) {
            return $this->masterEventDenied();
        }

        $role = $this->roleName($user);
        if ($role === 'super_admin') {
            return $this->masterEventCrud();
        }

        if ($role === 'admin' && in_array($this->placementName($user), ['MARKETING', 'MARCOM'], true)) {
            return $this->masterEventCrud();
        }

        if (in_array($this->departmentName($user), ['MARKETING', 'MARCOM'], true)) {
            return [
                'canView' => true,
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
            ];
        }

        return $this->masterEventDenied();
    }

    private function isActiveUser(?User $user): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        $user->loadMissing([
            'role:id,nama_role',
            'karyawan:id,nama,status_keaktifan,jabatan_id,departemen_id,penempatan_id',
            'karyawan.departemen:id,nama_departemen',
            'karyawan.penempatan:id,nama_penempatan',
        ]);

        return $user->role !== null
            && ($user->karyawan === null || $user->karyawan->status_keaktifan === 'aktif');
    }

    private function roleName(User $user): string
    {
        return mb_strtolower(trim((string) $user->role?->nama_role));
    }

    private function departmentName(User $user): string
    {
        return mb_strtoupper(trim((string) $user->karyawan?->departemen?->nama_departemen));
    }

    private function placementName(User $user): string
    {
        return mb_strtoupper(trim((string) $user->karyawan?->penempatan?->nama_penempatan));
    }

    private function closingEventCrud(): array
    {
        return ['canView' => true, 'canCreate' => true, 'canUpdate' => true, 'canDelete' => true, 'canExport' => true];
    }

    private function closingEventDenied(): array
    {
        return ['canView' => false, 'canCreate' => false, 'canUpdate' => false, 'canDelete' => false, 'canExport' => false];
    }

    private function masterEventCrud(): array
    {
        return ['canView' => true, 'canCreate' => true, 'canUpdate' => true, 'canDelete' => true];
    }

    private function masterEventDenied(): array
    {
        return ['canView' => false, 'canCreate' => false, 'canUpdate' => false, 'canDelete' => false];
    }
}
