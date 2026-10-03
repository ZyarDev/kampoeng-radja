<?php

namespace Tests\Feature\Admin;

use App\Models\Departemen;
use App\Models\Penempatan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_access_follows_final_role_department_and_placement_matrix(): void
    {
        $this->actingAs($this->actor('super_admin'))
            ->get(route('dashboard.cms.home'))
            ->assertOk();

        $this->actingAs($this->actor('admin', placement: 'MARCOM'))
            ->get(route('dashboard.cms.home'))
            ->assertOk();

        $this->actingAs($this->actor('user', department: 'MARCOM'))
            ->get(route('dashboard.cms.home'))
            ->assertOk();

        $this->actingAs($this->actor('admin', department: 'OPERASIONAL', placement: 'OPERASIONAL'))
            ->get(route('dashboard.cms.home'))
            ->assertForbidden();

        $this->actingAs($this->actor('user', department: 'OPERASIONAL', placement: 'MARCOM'))
            ->get(route('dashboard.cms.home'))
            ->assertForbidden();

        $this->actingAs($this->actor('user'))
            ->get(route('dashboard.cms.home'))
            ->assertForbidden();
    }

    private function actor(
        string $role,
        ?string $department = null,
        ?string $placement = null,
    ): User {
        $user = User::factory()->create([
            'role_id' => Role::firstOrCreate(['nama_role' => $role])->id,
        ]);

        if ($department !== null || $placement !== null) {
            $user->karyawan->update([
                'departemen_id' => $department === null
                    ? $user->karyawan->departemen_id
                    : Departemen::firstOrCreate(['nama_departemen' => $department])->id,
                'penempatan_id' => $placement === null
                    ? $user->karyawan->penempatan_id
                    : Penempatan::firstOrCreate(['nama_penempatan' => $placement])->id,
            ]);
        }

        return $user->refresh();
    }
}
