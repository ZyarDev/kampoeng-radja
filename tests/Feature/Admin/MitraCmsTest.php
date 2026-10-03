<?php

namespace Tests\Feature\Admin;

use App\Models\Mitra;
use App\Models\Penempatan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MitraCmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_and_super_admin_can_manage_partners(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('dashboard.cms.home'))->assertOk();
        $this->actingAs($this->userWithRole('super_admin'))->get(route('dashboard.cms.home'))->assertOk();
        $this->actingAs($this->userWithRole('user'))->post(route('dashboard.cms.home.partners.store'), [])->assertForbidden();
    }

    public function test_create_and_validation_follow_partner_contract(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->post(route('dashboard.cms.home.partners.store'), [
            'nama_brand' => 'Mitra Pengujian', 'logo' => $this->fakeImage('logo.png'),
            'is_active' => true, 'urutan_tampil' => 2,
        ])->assertRedirect(route('dashboard.cms.home'));

        $mitra = Mitra::query()->sole();
        $this->assertSame($admin->id, $mitra->created_by);
        Storage::disk('public')->assertExists($mitra->logo);

        $this->actingAs($admin)->post(route('dashboard.cms.home.partners.store'), [
            'nama_brand' => str_repeat('x', 101), 'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            'is_active' => true, 'urutan_tampil' => 1000,
        ])->assertSessionHasErrors(['nama_brand', 'logo', 'urutan_tampil']);
    }

    public function test_update_preserves_or_safely_replaces_logo(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $mitra = $this->createPartner($admin, 'partners/old.jpg');
        Storage::disk('public')->put($mitra->logo, 'old');

        $this->actingAs($admin)->patch(route('dashboard.cms.home.partners.update', $mitra), [
            'nama_brand' => 'Nama Baru', 'is_active' => true, 'urutan_tampil' => 3,
        ])->assertRedirect(route('dashboard.cms.home'));
        $this->assertSame('partners/old.jpg', $mitra->fresh()->logo);

        $this->actingAs($admin)->post(route('dashboard.cms.home.partners.update', $mitra), [
            '_method' => 'patch', 'nama_brand' => 'Nama Baru', 'is_active' => true, 'urutan_tampil' => 3,
            'logo' => $this->fakeImage('new.png'),
        ])->assertRedirect(route('dashboard.cms.home'));
        $mitra->refresh();
        Storage::disk('public')->assertExists($mitra->logo);
        Storage::disk('public')->assertMissing('partners/old.jpg');
    }

    public function test_delete_cleans_partner_logo(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $mitra = $this->createPartner($admin, 'partners/delete.jpg');
        Storage::disk('public')->put($mitra->logo, 'logo');

        $this->actingAs($admin)->delete(route('dashboard.cms.home.partners.destroy', $mitra))->assertRedirect(route('dashboard.cms.home'));
        $this->assertDatabaseMissing('mitra', ['id' => $mitra->id]);
        Storage::disk('public')->assertMissing('partners/delete.jpg');
    }

    public function test_home_only_exposes_active_partners_in_display_order(): void
    {
        $admin = $this->userWithRole('admin');
        $second = $this->createPartner($admin, 'partners/second.jpg', ['nama_brand' => 'Kedua', 'urutan_tampil' => 2]);
        $first = $this->createPartner($admin, 'partners/first.jpg', ['nama_brand' => 'Pertama', 'urutan_tampil' => 1]);
        $this->createPartner($admin, 'partners/hidden.jpg', ['nama_brand' => 'Hidden', 'is_active' => false, 'urutan_tampil' => 0]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->component('Home')->has('partners', 2)
            ->where('partners.0.id', $first->id)->where('partners.1.id', $second->id)
            ->where('partners.0.logo', '/storage/partners/first.jpg'));
    }

    private function createPartner(User $user, string $logo, array $overrides = []): Mitra
    {
        return Mitra::create([...[
            'nama_brand' => 'Mitra', 'logo' => $logo, 'is_active' => true,
            'urutan_tampil' => 0, 'created_by' => $user->id,
        ], ...$overrides]);
    }

    private function userWithRole(string $name): User
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => $name])->id]);

        if ($name === 'admin') {
            $user->karyawan->update([
                'penempatan_id' => Penempatan::firstOrCreate(['nama_penempatan' => 'MARCOM'])->id,
            ]);
        }

        return $user->refresh();
    }

    private function fakeImage(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    }
}
