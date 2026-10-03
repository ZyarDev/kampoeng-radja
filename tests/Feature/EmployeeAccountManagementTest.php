<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'user'] as $role) {
            Role::firstOrCreate(['nama_role' => $role]);
        }
    }

    public function test_only_super_admin_can_create_or_manage_employee_account(): void
    {
        $employee = $this->employeeWithoutAccount('Manager Marketing');

        foreach (['admin', 'user'] as $role) {
            $actor = $this->userWithRole($role);

            $this->actingAs($actor)->post(route('dashboard.karyawan.account.store', $employee), [
                'username' => "{$role}_forbidden",
                'pin' => '123456',
                'pin_confirmation' => '123456',
            ])->assertForbidden();
        }
    }

    public function test_super_admin_can_create_hashed_temporary_account_from_position_mapping(): void
    {
        $admin = $this->userWithRole('super_admin');
        $employee = $this->employeeWithoutAccount('Manager Marketing');

        $this->actingAs($admin)->post(route('dashboard.karyawan.account.store', $employee), [
            'username' => 'manager.account',
            'pin' => '654321',
            'pin_confirmation' => '654321',
            'role_id' => Role::firstOrCreate(['nama_role' => 'user'])->id,
        ])->assertSessionHas('success');

        $account = User::query()->where('karyawan_id', $employee->id)->firstOrFail();
        $this->assertSame('admin', mb_strtolower($account->role->nama_role));
        $this->assertTrue($account->must_change_pin);
        $this->assertTrue($account->is_active);
        $this->assertNotSame('654321', $account->getRawOriginal('pin'));
        $this->assertTrue(Hash::check('654321', $account->getRawOriginal('pin')));
    }

    public function test_account_role_comes_from_jabatan_role_and_ignores_client_role(): void
    {
        $admin = $this->userWithRole('super_admin');
        Role::firstOrCreate(['nama_role' => 'admin']);
        Role::firstOrCreate(['nama_role' => 'user']);

        $cases = [
            'Direktur Utama' => 'super_admin',
            'Manajer Marketing' => 'admin',
            'Operasional (OPS)' => 'user',
        ];

        foreach ($cases as $position => $expectedRole) {
            $employee = $this->employeeWithoutAccount($position, $expectedRole);
            $username = 'map_'.str()->random(12);

            $this->actingAs($admin)->post(route('dashboard.karyawan.account.store', $employee), [
                'username' => $username,
                'pin' => '123456',
                'pin_confirmation' => '123456',
                'role_id' => Role::where('nama_role', 'user')->value('id'),
            ])->assertSessionHas('success');

            $this->assertSame($expectedRole, mb_strtolower(User::where('username', $username)->firstOrFail()->role->nama_role));
        }
    }

    public function test_unmapped_position_and_second_account_are_rejected(): void
    {
        $admin = $this->userWithRole('super_admin');
        $unmapped = $this->employeeWithoutAccount('Operator Wahana', null);

        $this->actingAs($admin)->post(route('dashboard.karyawan.account.store', $unmapped), [
            'username' => 'operator.unmapped',
            'pin' => '123456',
            'pin_confirmation' => '123456',
        ])->assertSessionHasErrors('account');
        $this->assertDatabaseMissing('users', ['karyawan_id' => $unmapped->id]);

        $employee = $this->employeeWithoutAccount('Supervisor Operasional', 'admin');
        $this->createAccount($admin, $employee, 'supervisor.one');

        $this->actingAs($admin)->post(route('dashboard.karyawan.account.store', $employee), [
            'username' => 'supervisor.two',
            'pin' => '123456',
            'pin_confirmation' => '123456',
        ])->assertSessionHasErrors('account');
        $this->assertSame(1, User::where('karyawan_id', $employee->id)->count());
    }

    public function test_create_account_validates_unique_username_and_confirmed_six_digit_pin(): void
    {
        $admin = $this->userWithRole('super_admin');
        $employee = $this->employeeWithoutAccount('Manager Marketing');

        $this->actingAs($admin)->post(route('dashboard.karyawan.account.store', $employee), [
            'username' => $admin->username,
            'pin' => '12345',
            'pin_confirmation' => '999999',
        ])->assertSessionHasErrors(['username', 'pin']);

        $this->assertDatabaseMissing('users', ['karyawan_id' => $employee->id]);
    }

    public function test_account_status_can_be_managed_but_nonactive_employee_cannot_be_activated(): void
    {
        $admin = $this->userWithRole('super_admin');
        $employee = $this->employeeWithoutAccount('Manager Marketing');
        $this->createAccount($admin, $employee, 'status.account');
        $account = $employee->user()->firstOrFail();

        $this->actingAs($admin)->patch(route('dashboard.karyawan.account.status', $employee), ['is_active' => false])
            ->assertSessionHas('success');
        $this->assertFalse($account->refresh()->is_active);

        $employee->update(['status_keaktifan' => 'nonaktif']);
        $this->actingAs($admin)->patch(route('dashboard.karyawan.account.status', $employee), ['is_active' => true])
            ->assertSessionHasErrors('account');
        $this->assertFalse($account->refresh()->is_active);

        $employee->update(['status_keaktifan' => 'aktif']);
        $this->assertFalse($account->refresh()->is_active, 'Reaktivasi Karyawan tidak boleh otomatis mengaktifkan akun.');
        $this->actingAs($admin)->patch(route('dashboard.karyawan.account.status', $employee), ['is_active' => true])
            ->assertSessionHas('success');
        $this->assertTrue($account->refresh()->is_active);
    }

    public function test_temporary_pin_login_is_forced_to_change_pin_before_internal_access(): void
    {
        $temporaryUser = User::factory()->create([
            'pin' => '123456',
            'must_change_pin' => true,
        ]);

        $this->post('/login', ['username' => $temporaryUser->username, 'pin' => '123456'])
            ->assertRedirect(route('pin.change'));
        $this->assertAuthenticatedAs($temporaryUser);

        $this->get('/dashboard')->assertRedirect(route('pin.change'));
        $this->get('/dashboard/karyawan')->assertRedirect(route('pin.change'));
        $this->get('/admin/media-berita')->assertRedirect(route('pin.change'));
        $this->get(route('pin.change'))->assertInertia(fn (Assert $page) => $page->component('Auth/ChangePin'));

        $this->put(route('pin.update'), ['pin' => '654321', 'pin_confirmation' => '000000'])
            ->assertSessionHasErrors('pin');

        $this->put(route('pin.update'), ['pin' => '654321', 'pin_confirmation' => '654321'])
            ->assertRedirect(route('dashboard'));

        $temporaryUser->refresh();
        $this->assertFalse($temporaryUser->must_change_pin);
        $this->assertTrue(Hash::check('654321', $temporaryUser->getRawOriginal('pin')));
        $this->get('/dashboard')->assertOk();
    }

    public function test_nonactive_employee_cannot_login_even_if_account_flag_is_active(): void
    {
        $user = User::factory()->create(['pin' => '123456', 'is_active' => true]);
        $user->karyawan->update(['status_keaktifan' => 'nonaktif']);

        $this->post('/login', ['username' => $user->username, 'pin' => '123456']);

        $this->assertGuest();
    }

    public function test_account_payload_is_visible_only_to_super_admin(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $employee = $this->employeeWithoutAccount('Manager Marketing');
        $this->createAccount($superAdmin, $employee, 'payload.account');

        $this->actingAs($superAdmin)->get(route('dashboard.karyawan.show', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.canManageAccount', true)
                ->where('employee.account.username', 'payload.account')
                ->where('employee.account.roleName', 'admin')
                ->where('employee.account.mustChangePin', true)
                ->missing('employee.account.pin'));

        $departmentAdmin = $this->userWithRole('admin');
        $employee->update(['departemen_id' => $departmentAdmin->karyawan->departemen_id]);

        $this->actingAs($departmentAdmin)->get(route('dashboard.karyawan.show', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.canManageAccount', false)
                ->missing('employee.account')
                ->missing('employee.accountRole'));

        $regularUser = $this->userWithRole('user');
        $this->actingAs($regularUser)->get(route('dashboard.karyawan.show', $regularUser->karyawan))
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.canManageAccount', false)
                ->missing('employee.account')
                ->missing('employee.accountRole'));
    }

    public function test_super_admin_detail_without_account_keeps_management_section_contract(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $employee = $this->employeeWithoutAccount('Manager Marketing');

        $this->actingAs($superAdmin)->get(route('dashboard.karyawan.show', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Internal/Employee/Show')
                ->where('permissions.roleName', 'super_admin')
                ->where('permissions.canManageAccount', true)
                ->where('employee.account', null)
                ->where('employee.accountRole', 'admin')
                ->where('employee.accountRoleLabel', 'Admin'));
    }

    private function createAccount(User $admin, Karyawan $employee, string $username): void
    {
        $this->actingAs($admin)->post(route('dashboard.karyawan.account.store', $employee), [
            'username' => $username,
            'pin' => '123456',
            'pin_confirmation' => '123456',
        ])->assertSessionHas('success');
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['nama_role' => $roleName])->id,
            'must_change_pin' => false,
        ]);
    }

    private function employeeWithoutAccount(string $position, ?string $roleName = 'admin'): Karyawan
    {
        $user = User::factory()->create();
        $employee = $user->karyawan;
        $roleId = $roleName === null ? null : Role::where('nama_role', $roleName)->value('id');
        $jabatan = Jabatan::firstOrCreate(['nama_jabatan' => $position], ['role_id' => $roleId]);
        if ($jabatan->role_id !== $roleId) {
            $jabatan->update(['role_id' => $roleId]);
        }
        $employee->update([
            'jabatan_id' => $jabatan->id,
            'status_keaktifan' => 'aktif',
        ]);
        $user->delete();

        return $employee->refresh();
    }
}
