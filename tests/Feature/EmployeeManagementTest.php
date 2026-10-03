<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-17 09:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_guest_cannot_access_employee_pages(): void
    {
        $this->get('/dashboard/karyawan')->assertRedirect(route('login'));
        $this->get('/dashboard/jabatan-departemen')->assertRedirect(route('login'));
    }

    public function test_super_admin_sees_company_scope_and_sensitive_list_fields(): void
    {
        $admin = $this->userWithRole('super_admin');
        User::factory()->create();

        $this->actingAs($admin)->get('/dashboard/karyawan')->assertInertia(fn (Assert $page) => $page
            ->component('Internal/Employee/Index')
            ->where('permissions.canManage', true)
            ->has('employees.data', 2)
            ->has('employees.data.0.name')
            ->has('employees.data.0.gender')
            ->has('employees.data.0.religion')
            ->has('employees.data.0.birthDate')
            ->has('employees.data.0.birthPlace')
            ->has('employees.data.0.education')
            ->has('employees.data.0.position')
            ->has('employees.data.0.department')
            ->has('employees.data.0.employmentStatus')
            ->has('employees.data.0.activeStatus')
            ->has('employees.data.0.joinedAt')
            ->has('employees.data.0.leftAt')
            ->has('employees.data.0.nik'));

        $admin->karyawan->update(['foto_ktp' => 'employee-ktp/super-admin.jpg']);
        $this->actingAs($admin)->get(route('dashboard.karyawan.show', $admin->karyawan))->assertInertia(fn (Assert $page) => $page
            ->has('employee.name')->has('employee.gender')->has('employee.religion')
            ->has('employee.birthDate')->has('employee.birthPlace')->has('employee.education')
            ->has('employee.position')->has('employee.department')->has('employee.employmentStatus')
            ->has('employee.activeStatus')->has('employee.joinedAt')->has('employee.leftAt')
            ->has('employee.nik')->has('employee.address')->has('employee.maritalStatus')
            ->has('employee.phone')->where('employee.hasKtpPhoto', true)->has('employee.ktpPhotoUrl'));
    }

    public function test_admin_has_company_wide_scope_and_receives_no_sensitive_fields(): void
    {
        $admin = $this->userWithRole('admin');
        $same = User::factory()->create()->karyawan;
        $same->update(['departemen_id' => $admin->karyawan->departemen_id, 'nama' => 'Dalam Departemen']);
        $otherDepartment = Departemen::create(['nama_departemen' => 'Luar Scope']);
        $outside = User::factory()->create()->karyawan;
        $outside->update(['departemen_id' => $otherDepartment->id, 'nama' => 'Rahasia Luar']);

        $this->actingAs($admin)->get('/dashboard/karyawan')->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canManage', false)
            ->where('permissions.canSearch', true)
            ->where('masterData.departemen', fn ($items) => collect($items)->pluck('id')->contains($otherDepartment->id))
            ->has('employees.data', 3)
            ->where('employees.data', fn ($items) => collect($items)->pluck('name')->contains('Dalam Departemen')
                && collect($items)->pluck('name')->contains('Rahasia Luar'))
            ->has('employees.data.0.name')->has('employees.data.0.gender')->has('employees.data.0.religion')
            ->has('employees.data.0.birthDate')->has('employees.data.0.birthPlace')->has('employees.data.0.education')
            ->has('employees.data.0.position')->has('employees.data.0.department')->has('employees.data.0.employmentStatus')
            ->has('employees.data.0.activeStatus')->has('employees.data.0.joinedAt')->has('employees.data.0.leftAt')
            ->missing('employees.data.0.nik')->missing('employees.data.0.address')
            ->missing('employees.data.0.maritalStatus')->has('employees.data.0.phone')
            ->missing('employees.data.0.hasKtpPhoto')->missing('employees.data.0.ktpPhotoUrl'));

        $this->actingAs($admin)->get(route('dashboard.karyawan.show', $same))->assertInertia(fn (Assert $page) => $page
            ->has('employee.name')->has('employee.gender')->has('employee.religion')
            ->has('employee.birthDate')->has('employee.birthPlace')->has('employee.education')
            ->has('employee.position')->has('employee.department')->has('employee.employmentStatus')
            ->has('employee.activeStatus')->has('employee.joinedAt')->has('employee.leftAt')
            ->missing('employee.nik')->missing('employee.address')->missing('employee.maritalStatus')
            ->has('employee.phone')->missing('employee.hasKtpPhoto')->missing('employee.ktpPhotoUrl')
            ->missing('employee.account')->missing('employee.accountRole'));
        $this->actingAs($admin)->get(route('dashboard.karyawan.show', $outside))->assertInertia(fn (Assert $page) => $page
            ->where('employee.name', 'Rahasia Luar')
            ->has('employee.department')
            ->missing('employee.nik')->missing('employee.address')->missing('employee.maritalStatus')
            ->has('employee.phone')->missing('employee.hasKtpPhoto')->missing('employee.ktpPhotoUrl'));

        $this->actingAs($admin)->get('/dashboard/karyawan?departemen_id='.$otherDepartment->id)->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $outside->id)
            ->missing('employees.data.0.nik'));
    }

    public function test_admin_without_department_still_has_company_wide_scope(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->karyawan->update(['departemen_id' => null]);
        $outside = User::factory()->create()->karyawan;

        $this->actingAs($admin)->get('/dashboard/karyawan')->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 2)
            ->where('employees.data', fn ($items) => collect($items)->pluck('id')->contains($admin->karyawan_id)
                && collect($items)->pluck('id')->contains($outside->id)));
    }

    public function test_user_has_company_wide_read_only_scope_and_mutation_endpoints_are_forbidden(): void
    {
        $user = $this->userWithRole('user');
        $outside = User::factory()->create()->karyawan;

        $this->actingAs($user)->get('/dashboard/karyawan')->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canSearch', true)
            ->has('employees.data', 2)
            ->where('employees.data', fn ($items) => collect($items)->pluck('id')->contains($user->karyawan_id)
                && collect($items)->pluck('id')->contains($outside->id))
            ->has('employees.data.0.name')->has('employees.data.0.gender')->has('employees.data.0.religion')
            ->has('employees.data.0.birthDate')->has('employees.data.0.birthPlace')->has('employees.data.0.education')
            ->has('employees.data.0.position')->has('employees.data.0.department')->has('employees.data.0.employmentStatus')
            ->has('employees.data.0.activeStatus')->has('employees.data.0.joinedAt')->has('employees.data.0.leftAt')
            ->missing('employees.data.0.nik')->missing('employees.data.0.address')
            ->missing('employees.data.0.maritalStatus')->has('employees.data.0.phone')
            ->missing('employees.data.0.hasKtpPhoto')->missing('employees.data.0.ktpPhotoUrl'));
        $this->actingAs($user)->get(route('dashboard.karyawan.show', $user->karyawan))->assertInertia(fn (Assert $page) => $page
            ->has('employee.name')->has('employee.gender')->has('employee.religion')
            ->has('employee.birthDate')->has('employee.birthPlace')->has('employee.education')
            ->has('employee.position')->has('employee.department')->has('employee.employmentStatus')
            ->has('employee.activeStatus')->has('employee.joinedAt')->has('employee.leftAt')
            ->missing('employee.nik')->missing('employee.address')->missing('employee.maritalStatus')
            ->has('employee.phone')->missing('employee.hasKtpPhoto')->missing('employee.ktpPhotoUrl')
            ->missing('employee.account')->missing('employee.accountRole'));
        $this->actingAs($user)->get(route('dashboard.karyawan.show', $outside))->assertInertia(fn (Assert $page) => $page
            ->where('employee.id', $outside->id)
            ->has('employee.name')->has('employee.gender')->has('employee.religion')
            ->has('employee.birthDate')->has('employee.birthPlace')->has('employee.education')
            ->has('employee.position')->has('employee.department')->has('employee.employmentStatus')
            ->has('employee.activeStatus')->has('employee.joinedAt')->has('employee.leftAt')
            ->missing('employee.nik')->missing('employee.address')->missing('employee.maritalStatus')
            ->has('employee.phone')->missing('employee.hasKtpPhoto')->missing('employee.ktpPhotoUrl')
            ->missing('employee.account')->missing('employee.accountRole'));
        $this->actingAs($user)->get('/dashboard/karyawan/create')->assertForbidden();
        $this->actingAs($user)->post('/dashboard/karyawan', [])->assertForbidden();
        $this->actingAs($user)->get('/dashboard/jabatan-departemen')->assertForbidden();
    }

    public function test_super_admin_can_create_employee_with_nullable_department_and_private_ktp(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('super_admin');
        $data = $this->validEmployeeData(['departemen_id' => null, 'foto_ktp' => UploadedFile::fake()->create('ktp.webp', 100, 'image/webp')]);

        $response = $this->actingAs($admin)->post('/dashboard/karyawan', $data);
        $employee = Karyawan::query()->where('nik', $data['nik'])->firstOrFail();

        $response->assertRedirect(route('dashboard.karyawan.show', $employee));
        $this->assertNull($employee->departemen_id);
        Storage::disk('local')->assertExists($employee->foto_ktp);
        $this->actingAs($admin)->get(route('dashboard.karyawan.photo', $employee))->assertOk();
        $this->actingAs($this->userWithRole('admin'))->get(route('dashboard.karyawan.photo', $employee))->assertForbidden();
    }

    public function test_create_validation_rejects_duplicate_nik_and_invalid_foreign_keys(): void
    {
        $admin = $this->userWithRole('super_admin');
        $data = $this->validEmployeeData([
            'nik' => $admin->karyawan->nik,
            'jabatan_id' => 999999,
            'departemen_id' => 999999,
        ]);

        $this->actingAs($admin)->post('/dashboard/karyawan', $data)
            ->assertSessionHasErrors(['nik', 'jabatan_id', 'departemen_id']);
    }

    public function test_super_admin_can_update_employee_and_replace_private_ktp(): void
    {
        Storage::fake('local');
        $admin = $this->userWithRole('super_admin');
        $employee = $this->employeeWithoutUser(['foto_ktp' => 'employee-ktp/old.jpg']);
        Storage::disk('local')->put($employee->foto_ktp, 'old');

        $data = $this->validEmployeeData([
            'nik' => $employee->nik,
            'nama' => 'Nama Diperbarui',
            'foto_ktp' => UploadedFile::fake()->create('new.png', 100, 'image/png'),
        ]);
        $this->actingAs($admin)->put(route('dashboard.karyawan.update', $employee), $data)
            ->assertRedirect(route('dashboard.karyawan.show', $employee));

        $employee->refresh();
        $this->assertSame('Nama Diperbarui', $employee->nama);
        Storage::disk('local')->assertMissing('employee-ktp/old.jpg');
        Storage::disk('local')->assertExists($employee->foto_ktp);
    }

    public function test_changing_employee_position_syncs_existing_account_role_without_changing_credentials(): void
    {
        $admin = $this->userWithRole('super_admin');
        $adminRole = Role::firstOrCreate(['nama_role' => 'admin']);
        $superAdminRole = Role::firstOrCreate(['nama_role' => 'super_admin']);
        $oldPosition = Jabatan::create(['nama_jabatan' => 'Manager Sync Test', 'role_id' => $adminRole->id]);
        $newPosition = Jabatan::create(['nama_jabatan' => 'Direktur Sync Test', 'role_id' => $superAdminRole->id]);
        $account = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $employee = $account->karyawan;
        $employee->update(['jabatan_id' => $oldPosition->id]);
        $username = $account->username;
        $pinHash = $account->getRawOriginal('pin');

        $this->actingAs($admin)->put(route('dashboard.karyawan.update', $employee), $this->validEmployeeData([
            'nik' => $employee->nik,
            'nama' => $employee->nama,
            'jabatan_id' => $newPosition->id,
        ]))->assertRedirect(route('dashboard.karyawan.show', $employee));

        $account->refresh();
        $this->assertSame($superAdminRole->id, $account->role_id);
        $this->assertSame($username, $account->username);
        $this->assertSame($pinHash, $account->getRawOriginal('pin'));
        $this->assertTrue((bool) $account->is_active);
    }

    public function test_employee_without_account_can_change_to_position_without_role(): void
    {
        $admin = $this->userWithRole('super_admin');
        $employee = $this->employeeWithoutUser();
        $position = Jabatan::create(['nama_jabatan' => 'No Role Sync Test', 'role_id' => null]);

        $this->actingAs($admin)->put(route('dashboard.karyawan.update', $employee), $this->validEmployeeData([
            'nik' => $employee->nik,
            'nama' => $employee->nama,
            'jabatan_id' => $position->id,
        ]))->assertRedirect(route('dashboard.karyawan.show', $employee));

        $this->assertSame($position->id, $employee->refresh()->jabatan_id);
    }

    public function test_existing_account_cannot_change_to_position_without_role(): void
    {
        $admin = $this->userWithRole('super_admin');
        $account = User::factory()->create();
        $employee = $account->karyawan;
        $position = Jabatan::create(['nama_jabatan' => 'Invalid Role Sync Test', 'role_id' => null]);
        $originalPosition = $employee->jabatan_id;
        $originalRole = $account->role_id;

        $this->actingAs($admin)->put(route('dashboard.karyawan.update', $employee), $this->validEmployeeData([
            'nik' => $employee->nik,
            'nama' => $employee->nama,
            'jabatan_id' => $position->id,
        ]))->assertSessionHasErrors('jabatan_id');

        $this->assertSame($originalPosition, $employee->refresh()->jabatan_id);
        $this->assertSame($originalRole, $account->refresh()->role_id);
    }

    public function test_deactivation_preserves_attendance_and_disables_existing_user(): void
    {
        $admin = $this->userWithRole('super_admin');
        $employeeUser = User::factory()->create();
        Absensi::create(['karyawan_id' => $employeeUser->karyawan_id, 'tanggal_absensi' => '2026-08-17', 'status_kehadiran' => 'H']);

        $this->actingAs($admin)->patch(route('dashboard.karyawan.exit', $employeeUser->karyawan), [
            'tanggal_keluar' => '2026-08-17',
        ])->assertRedirect();

        $this->assertDatabaseHas('karyawan', ['id' => $employeeUser->karyawan_id, 'status_keaktifan' => 'nonaktif']);
        $this->assertDatabaseHas('users', ['id' => $employeeUser->id, 'is_active' => false]);
        $this->assertDatabaseHas('absensi', ['karyawan_id' => $employeeUser->karyawan_id, 'status_kehadiran' => 'H']);
    }

    public function test_exit_disables_account_and_reactivating_employee_does_not_reactivate_account(): void
    {
        $admin = $this->userWithRole('super_admin');
        $employeeUser = User::factory()->create();

        $this->actingAs($admin)->patch(route('dashboard.karyawan.exit', $employeeUser->karyawan), [
            'tanggal_keluar' => '2026-08-17',
        ])->assertSessionHas('success');
        $employeeUser->karyawan->refresh();
        $this->assertSame('nonaktif', $employeeUser->karyawan->status_keaktifan);
        $this->assertSame('2026-08-17', $employeeUser->karyawan->tanggal_keluar->toDateString());
        $this->assertDatabaseHas('users', ['id' => $employeeUser->id, 'is_active' => false]);

        $data = $this->validEmployeeData([
            'nik' => $employeeUser->karyawan->nik,
            'nama' => $employeeUser->karyawan->nama,
            'jabatan_id' => $employeeUser->karyawan->jabatan_id,
            'departemen_id' => $employeeUser->karyawan->departemen_id,
            'status_keaktifan' => 'aktif',
            'tanggal_keluar' => null,
        ]);
        $this->actingAs($admin)->put(route('dashboard.karyawan.update', $employeeUser->karyawan), $data)->assertSessionHas('success');
        $this->assertDatabaseHas('karyawan', ['id' => $employeeUser->karyawan_id, 'status_keaktifan' => 'aktif']);
        $this->assertDatabaseHas('users', ['id' => $employeeUser->id, 'is_active' => false]);
    }

    public function test_conditional_delete_allows_clean_record_and_rejects_user_or_attendance_dependencies(): void
    {
        $admin = $this->userWithRole('super_admin');
        $clean = $this->employeeWithoutUser();
        $this->actingAs($admin)->delete(route('dashboard.karyawan.destroy', $clean))->assertRedirect(route('dashboard.karyawan.index'));
        $this->assertDatabaseMissing('karyawan', ['id' => $clean->id]);

        $withUser = User::factory()->create()->karyawan;
        $this->actingAs($admin)->delete(route('dashboard.karyawan.destroy', $withUser))->assertSessionHasErrors('employee');
        $this->assertDatabaseHas('karyawan', ['id' => $withUser->id]);

        $withAttendance = $this->employeeWithoutUser();
        Absensi::create(['karyawan_id' => $withAttendance->id, 'tanggal_absensi' => '2026-08-17', 'status_kehadiran' => 'H']);
        $this->actingAs($admin)->delete(route('dashboard.karyawan.destroy', $withAttendance))->assertSessionHasErrors('employee');
        $this->assertDatabaseHas('karyawan', ['id' => $withAttendance->id]);
    }

    public function test_super_admin_can_manage_masters_and_used_master_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('super_admin');
        $this->actingAs($admin)->post(route('dashboard.jabatan.store'), ['nama_jabatan' => 'Teknisi'])->assertSessionHas('success');
        $position = Jabatan::where('nama_jabatan', 'Teknisi')->firstOrFail();
        $this->actingAs($admin)->post(route('dashboard.jabatan.store'), ['nama_jabatan' => 'Teknisi'])->assertSessionHasErrors('nama_jabatan');
        $this->actingAs($admin)->put(route('dashboard.jabatan.update', $position), ['nama_jabatan' => 'Teknisi Senior'])->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('dashboard.jabatan.destroy', $position))->assertSessionHas('success');

        $usedDepartment = $admin->karyawan->departemen;
        $this->actingAs($admin)->delete(route('dashboard.departemen.destroy', $usedDepartment))
            ->assertSessionHas('error', 'Tidak dapat dihapus karena data masih digunakan.');

        $this->actingAs($admin)->post(route('dashboard.departemen.store'), ['nama_departemen' => 'Operasional Baru'])->assertSessionHas('success');
        $department = Departemen::where('nama_departemen', 'Operasional Baru')->firstOrFail();
        $this->actingAs($admin)->put(route('dashboard.departemen.update', $department), ['nama_departemen' => 'Operasional Utama'])->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('dashboard.departemen.destroy', $department))->assertSessionHas('success');

        foreach (['admin', 'user'] as $role) {
            $this->actingAs($this->userWithRole($role))->post(route('dashboard.departemen.store'), ['nama_departemen' => 'Terlarang'])->assertForbidden();
        }
    }

    public function test_absensi_excludes_active_employee_whose_join_date_is_after_selected_day(): void
    {
        $admin = $this->userWithRole('super_admin');
        $future = $this->employeeWithoutUser(['tanggal_masuk' => '2026-08-18']);

        $this->actingAs($admin)->get('/admin/absensi')->assertInertia(fn (Assert $page) => $page
            ->where('employees', fn ($items) => ! collect($items)->pluck('id')->contains($future->id)));
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => $roleName])->id]);
    }

    private function employeeWithoutUser(array $overrides = []): Karyawan
    {
        $template = User::factory()->create()->karyawan;
        $clone = $template->replicate();
        $clone->fill([...$overrides, 'nik' => $overrides['nik'] ?? fake()->unique()->numerify('EMP###############')]);
        $clone->save();
        $template->user->delete();
        $template->delete();

        return $clone;
    }

    private function validEmployeeData(array $overrides = []): array
    {
        return [...[
            'nik' => 'EMP00000000000000001', 'nama' => 'Karyawan Baru', 'tanggal_lahir' => '2000-01-01',
            'tempat_lahir' => 'Jambi', 'jenis_kelamin' => 'L', 'alamat' => 'Alamat pengujian', 'agama' => 'islam',
            'status_perkawinan' => 'belum kawin', 'pendidikan' => 'S1',
            'jabatan_id' => Jabatan::firstOrCreate(['nama_jabatan' => 'Staf Test'])->id,
            'departemen_id' => Departemen::firstOrCreate(['nama_departemen' => 'Departemen Test'])->id,
            'status_keaktifan' => 'aktif', 'status_kerja' => 'kontrak', 'tanggal_masuk' => '2026-08-17',
            'tanggal_keluar' => null, 'no_hp' => '080000000001', 'foto_ktp' => null,
        ], ...$overrides];
    }
}
