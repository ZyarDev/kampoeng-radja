<?php

namespace Tests\Feature\Admin;

use App\Models\GaleriEvent;
use App\Models\GaleriEventFoto;
use App\Models\Penempatan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GaleriEventCmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_gallery_access_is_limited_to_admin_and_super_admin(): void
    {
        $this->get(route('dashboard.cms.gallery.index'))->assertRedirect(route('login'));

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('dashboard.cms.gallery.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Internal/CMS/Gallery/Index')
                ->has('items', 0));

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('dashboard.cms.gallery.index'))
            ->assertOk();

        $this->actingAs($this->userWithRole('user'))
            ->get(route('dashboard.cms.gallery.index'))
            ->assertForbidden();
    }

    public function test_admin_can_manage_album_and_multiple_photos_with_safe_file_cleanup(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('dashboard.cms.gallery.store'), [
                ...$this->validData(),
                'fotos' => [
                    $this->fakeImage('cover.png'),
                    $this->fakeImage('middle.png'),
                    $this->fakeImage('last.png'),
                ],
                'new_photo_captions' => ['Foto pembuka', '', 'Foto penutup'],
            ])
            ->assertRedirect(route('dashboard.cms.gallery.index'));

        $event = GaleriEvent::query()->sole();
        $photos = $event->photos()->get()->values();

        $this->assertSame($admin->id, $event->created_by);
        $this->assertCount(3, $photos);
        $this->assertSame([0, 1, 2], $photos->pluck('urutan')->all());
        $this->assertSame('Foto pembuka', $photos[0]->caption);
        $this->assertNull($photos[1]->caption);
        $photos->each(fn (GaleriEventFoto $photo) => Storage::disk('public')->assertExists($photo->foto));

        $removedPath = $photos[1]->foto;
        $this->actingAs($admin)
            ->post(route('dashboard.cms.gallery.update', $event), [
                ...$this->validData([
                    'nama_event' => 'Hari Kemerdekaan Diperbarui',
                    'deskripsi' => 'Deskripsi album sudah diperbarui.',
                ]),
                '_method' => 'patch',
                'existing_photos' => [
                    ['id' => $photos[2]->id, 'caption' => 'Sekarang menjadi cover'],
                    ['id' => $photos[0]->id, 'caption' => 'Pindah ke urutan kedua'],
                ],
                'fotos' => [$this->fakeImage('new-photo.webp')],
                'new_photo_captions' => ['Foto tambahan'],
            ])
            ->assertRedirect(route('dashboard.cms.gallery.index'));

        $event->refresh();
        $updatedPhotos = $event->photos()->get()->values();
        $this->assertSame('Hari Kemerdekaan Diperbarui', $event->nama_event);
        $this->assertSame($admin->id, $event->updated_by);
        $this->assertCount(3, $updatedPhotos);
        $this->assertSame($photos[2]->id, $updatedPhotos[0]->id);
        $this->assertSame('Sekarang menjadi cover', $updatedPhotos[0]->caption);
        $this->assertSame('Foto tambahan', $updatedPhotos[2]->caption);
        $this->assertSame([0, 1, 2], $updatedPhotos->pluck('urutan')->all());
        $this->assertDatabaseMissing('galeri_event_foto', ['id' => $photos[1]->id]);
        Storage::disk('public')->assertMissing($removedPath);

        $this->actingAs($admin)
            ->get(route('dashboard.cms.gallery.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.nama_event', 'Hari Kemerdekaan Diperbarui')
                ->where('items.0.photo_count', 3)
                ->where('items.0.cover_url', Storage::disk('public')->url($updatedPhotos[0]->foto))
                ->where('items.0.photos.0.caption', 'Sekarang menjadi cover')
                ->missing('items.0.photos.0.foto'));

        $remainingPaths = $updatedPhotos->pluck('foto');
        $this->actingAs($admin)
            ->delete(route('dashboard.cms.gallery.destroy', $event))
            ->assertRedirect(route('dashboard.cms.gallery.index'));

        $this->assertDatabaseMissing('galeri_event', ['id' => $event->id]);
        $this->assertDatabaseMissing('galeri_event_foto', ['galeri_event_id' => $event->id]);
        $remainingPaths->each(fn (string $path) => Storage::disk('public')->assertMissing($path));
    }

    public function test_new_album_requires_at_least_one_valid_photo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('dashboard.cms.gallery.store'), $this->validData())
            ->assertSessionHasErrors([
                'fotos' => 'Minimal satu foto wajib ditambahkan untuk Galeri Event baru.',
            ]);

        $this->assertDatabaseCount('galeri_event', 0);
    }

    public function test_public_gallery_receives_stably_sorted_album_and_photo_payload(): void
    {
        $creator = $this->userWithRole('admin');
        $olderId = $this->createEvent($creator, 'Album Pertama', '2026-08-17');
        $newerId = $this->createEvent($creator, 'Album Kedua', '2026-08-17');

        GaleriEventFoto::create([
            'galeri_event_id' => $newerId->id,
            'created_by' => $creator->id,
            'foto' => 'galeri-event/foto-kedua.jpg',
            'caption' => null,
            'urutan' => 10,
        ]);
        GaleriEventFoto::create([
            'galeri_event_id' => $newerId->id,
            'created_by' => $creator->id,
            'foto' => 'galeri-event/featured.jpg',
            'caption' => 'Featured default',
            'urutan' => 1,
        ]);

        $this->get(route('galeri-event'))->assertInertia(fn (Assert $page) => $page
            ->component('GaleriEvent')
            ->has('events', 2)
            ->where('events.0.id', $newerId->id)
            ->where('events.0.nama_event', 'Album Kedua')
            ->where('events.0.tanggal_event', '2026-08-17')
            ->where('events.0.deskripsi', 'Deskripsi Album Kedua')
            ->where('events.0.photos.0.url', '/storage/galeri-event/featured.jpg')
            ->where('events.0.photos.0.caption', 'Featured default')
            ->where('events.0.photos.0.urutan', 1)
            ->where('events.0.photos.1.url', '/storage/galeri-event/foto-kedua.jpg')
            ->where('events.1.id', $olderId->id)
            ->missing('events.0.photos.0.foto'));
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'role_id' => Role::firstOrCreate(['nama_role' => $roleName])->id,
        ]);

        if ($roleName === 'admin') {
            $user->karyawan->update([
                'penempatan_id' => Penempatan::firstOrCreate(['nama_penempatan' => 'MARCOM'])->id,
            ]);
        }

        return $user->refresh();
    }

    private function validData(array $overrides = []): array
    {
        return [
            'nama_event' => 'Hari Kemerdekaan 2026',
            'tanggal_event' => '2026-08-17',
            'deskripsi' => 'Dokumentasi kegiatan Hari Kemerdekaan Kampoeng Radja.',
            ...$overrides,
        ];
    }

    private function createEvent(User $creator, string $name, string $date): GaleriEvent
    {
        return GaleriEvent::create([
            'created_by' => $creator->id,
            'updated_by' => null,
            'nama_event' => $name,
            'tanggal_event' => $date,
            'deskripsi' => "Deskripsi {$name}",
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
