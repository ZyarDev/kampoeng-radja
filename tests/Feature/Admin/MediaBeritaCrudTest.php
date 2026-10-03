<?php

namespace Tests\Feature\Admin;

use App\Models\MediaBerita;
use App\Models\Penempatan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MediaBeritaCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_media_berita_crud(): void
    {
        $this->get('/admin/media-berita')->assertRedirect(route('login'));
        $this->get('/admin/media-berita/create')->assertRedirect(route('login'));
        $this->post('/admin/media-berita', [])->assertRedirect(route('login'));
        $this->get('/admin/media-berita/1/edit')->assertRedirect(route('login'));
        $this->patch('/admin/media-berita/1', [])->assertRedirect(route('login'));
        $this->delete('/admin/media-berita/1')->assertRedirect(route('login'));
    }

    public function test_user_role_cannot_access_media_berita_crud(): void
    {
        $user = $this->userWithRole('user');
        $item = $this->createNews($user, 'media-berita/forbidden.jpg');

        $this->actingAs($user)->get('/admin/media-berita')->assertForbidden();
        $this->actingAs($user)->get('/admin/media-berita/create')->assertForbidden();
        $this->actingAs($user)->post('/admin/media-berita', [])->assertForbidden();
        $this->actingAs($user)->get(route('admin.media-berita.edit', $item))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.media-berita.update', $item), [])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.media-berita.destroy', $item))->assertForbidden();
    }

    public function test_admin_can_open_media_berita_index(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('dashboard.cms.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Internal/CMS/Home/Index')
                ->has('newsItems'));
    }

    public function test_super_admin_can_open_media_berita_index(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('dashboard.cms.home'))
            ->assertInertia(fn (Assert $page) => $page->component('Internal/CMS/Home/Index'));
    }

    public function test_admin_can_create_news_with_uploaded_photo_and_server_side_audit(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->post(route('dashboard.cms.home.media.store'), [
            'judul' => 'Informasi Pengujian',
            'deskripsi' => 'Deskripsi berita untuk pengujian.',
            'foto' => $this->fakeImage('berita.png'),
            'tanggal_publish' => '2026-08-12 10:30:00',
            'created_by' => 999999,
            'updated_by' => 999999,
        ]);

        $response->assertRedirect(route('dashboard.cms.home'));

        $item = MediaBerita::query()->sole();
        $this->assertSame($admin->id, $item->created_by);
        $this->assertNull($item->updated_by);
        $this->assertStringStartsWith('media-berita/', $item->foto);
        Storage::disk('public')->assertExists($item->foto);
    }

    public function test_create_validation_requires_valid_fields_and_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('dashboard.cms.home.media.store'), [
                'judul' => str_repeat('a', 151),
                'deskripsi' => str_repeat('d', 251),
                'foto' => UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'),
                'tanggal_publish' => 'bukan-tanggal',
            ])
            ->assertSessionHasErrors(['judul', 'deskripsi', 'foto', 'tanggal_publish']);

        $this->assertDatabaseCount('media_berita', 0);
    }

    public function test_admin_can_update_news_without_replacing_photo_and_sets_updated_by(): void
    {
        Storage::fake('public');
        $creator = $this->userWithRole('super_admin');
        $admin = $this->userWithRole('admin');
        $item = $this->createNews($creator, 'media-berita/original.jpg');
        Storage::disk('public')->put($item->foto, 'original');

        $this->actingAs($admin)->patch(route('dashboard.cms.home.media.update', $item), [
            'judul' => 'Judul Diperbarui',
            'deskripsi' => 'Deskripsi diperbarui.',
            'tanggal_publish' => '2026-08-13 11:00:00',
        ])->assertRedirect(route('dashboard.cms.home'));

        $item->refresh();
        $this->assertSame('Judul Diperbarui', $item->judul);
        $this->assertSame($creator->id, $item->created_by);
        $this->assertSame($admin->id, $item->updated_by);
        $this->assertSame('media-berita/original.jpg', $item->foto);
        Storage::disk('public')->assertExists('media-berita/original.jpg');
    }

    public function test_replacing_photo_stores_new_file_and_removes_unused_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $item = $this->createNews($admin, 'media-berita/old.jpg');
        Storage::disk('public')->put($item->foto, 'old');

        $this->actingAs($admin)->post(route('dashboard.cms.home.media.update', $item), [
            '_method' => 'patch',
            'judul' => $item->judul,
            'deskripsi' => $item->deskripsi,
            'tanggal_publish' => '2026-08-14 08:00:00',
            'foto' => $this->fakeImage('replacement.png'),
        ])->assertRedirect(route('dashboard.cms.home'));

        $newPath = $item->fresh()->foto;
        $this->assertNotSame('media-berita/old.jpg', $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing('media-berita/old.jpg');
    }

    public function test_admin_can_delete_news_and_its_unused_photo(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $item = $this->createNews($admin, 'media-berita/delete-me.jpg');
        Storage::disk('public')->put($item->foto, 'photo');

        $this->actingAs($admin)
            ->delete(route('dashboard.cms.home.media.destroy', $item))
            ->assertRedirect(route('dashboard.cms.home'));

        $this->assertDatabaseMissing('media_berita', ['id' => $item->id]);
        Storage::disk('public')->assertMissing('media-berita/delete-me.jpg');
    }

    public function test_delete_preserves_photo_still_used_by_another_record(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $first = $this->createNews($admin, 'media-berita/shared.jpg');
        $this->createNews($admin, 'media-berita/shared.jpg', 'Berita Kedua');
        Storage::disk('public')->put('media-berita/shared.jpg', 'shared');

        $this->actingAs($admin)->delete(route('dashboard.cms.home.media.destroy', $first));

        Storage::disk('public')->assertExists('media-berita/shared.jpg');
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['nama_role' => $roleName]);

        $user = User::factory()->create(['role_id' => $role->id]);

        if ($roleName === 'admin') {
            $user->karyawan->update([
                'penempatan_id' => Penempatan::firstOrCreate(['nama_penempatan' => 'MARCOM'])->id,
            ]);
        }

        return $user->refresh();
    }

    private function createNews(User $creator, string $photo, string $title = 'Berita Pengujian'): MediaBerita
    {
        return MediaBerita::create([
            'created_by' => $creator->id,
            'updated_by' => null,
            'judul' => $title,
            'deskripsi' => 'Deskripsi berita untuk pengujian.',
            'foto' => $photo,
            'tanggal_publish' => '2026-08-12 10:00:00',
        ]);
    }

    private function fakeImage(string $name): UploadedFile
    {
        $onePixelPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        return UploadedFile::fake()->createWithContent($name, $onePixelPng);
    }
}
