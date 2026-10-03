<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_admin_area(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_active_user_role_is_redirected_from_legacy_admin_entry_to_dashboard(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user)->get('/admin')->assertRedirect('/dashboard');
    }

    public function test_admin_role_is_redirected_from_legacy_admin_entry_to_dashboard(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->get('/admin')->assertRedirect('/dashboard');
    }

    public function test_super_admin_role_is_redirected_from_legacy_admin_entry_to_dashboard(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->actingAs($user)->get('/admin')->assertRedirect('/dashboard');
    }

    public function test_inactive_authenticated_user_is_forbidden_from_admin_area(): void
    {
        $user = $this->userWithRole('admin', false);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    private function userWithRole(string $roleName, bool $isActive = true): User
    {
        $role = Role::firstOrCreate(['nama_role' => $roleName]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => $isActive,
        ]);
    }
}
