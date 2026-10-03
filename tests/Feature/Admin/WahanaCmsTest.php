<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Penempatan;
use App\Models\User;
use App\Models\Wahana;
use App\Models\WahanaFoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WahanaCmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_wahana_access_is_limited_to_admin_and_super_admin(): void
    {
        $this->get(route('dashboard.cms.wahana.index'))->assertRedirect(route('login'));

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('dashboard.cms.wahana.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Internal/CMS/Wahana/Index')
                ->where('featuredLimit', 3)
                ->has('labels', 6));

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('dashboard.cms.wahana.index'))
            ->assertOk();

        $this->actingAs($this->userWithRole('user'))
            ->get(route('dashboard.cms.wahana.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_update_toggle_and_delete_wahana_with_safe_photo_cleanup(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('dashboard.cms.wahana.store'), [
                ...$this->validData(),
                'fotos' => [
                    $this->fakeImage('flying-fox.png'),
                    $this->fakeImage('flying-fox-detail.png'),
                ],
                'label' => ['Air', 'Anak-anak', 'Santai'],
            ])
            ->assertRedirect(route('dashboard.cms.wahana.index'));

        $wahana = Wahana::query()->sole();
        $this->assertSame('Air,Anak-anak,Santai', $wahana->label);
        $this->assertTrue($wahana->is_active);
        $this->assertTrue($wahana->is_unggulan);
        $this->assertCount(2, $wahana->fotos);
        Storage::disk('public')->assertExists($wahana->foto);
        $oldPhotos = $wahana->fotos->pluck('foto');
        $secondPhoto = $wahana->fotos->sortBy('urutan')->values()[1];

        $this->actingAs($admin)
            ->post(route('dashboard.cms.wahana.update', $wahana), [
                ...$this->validData(['deskripsi_singkat' => 'Teks berubah tanpa upload ulang foto.']),
                '_method' => 'patch',
                'existing_photo_order' => $wahana->fotos->pluck('id')->map(fn (int $id): string => (string) $id)->all(),
            ])
            ->assertRedirect(route('dashboard.cms.wahana.index'));

        $this->assertSame('Teks berubah tanpa upload ulang foto.', $wahana->fresh()->deskripsi_singkat);
        $this->assertCount(2, $wahana->fresh()->fotos);

        $this->actingAs($admin)
            ->post(route('dashboard.cms.wahana.update', $wahana), [
                ...$this->validData([
                    'nama_wahana' => 'Flying Fox Baru',
                    'label' => ['Darat', 'Dewasa'],
                    'is_unggulan' => false,
                ]),
                '_method' => 'patch',
                'existing_photo_order' => [(string) $secondPhoto->id],
                'fotos' => [$this->fakeImage('replacement.webp')],
            ])
            ->assertRedirect(route('dashboard.cms.wahana.index'));

        $wahana->refresh();
        $this->assertSame('Flying Fox Baru', $wahana->nama_wahana);
        $this->assertSame('Darat,Dewasa', $wahana->label);
        $this->assertSame($admin->id, $wahana->updated_by);
        $this->assertCount(2, $wahana->fotos);
        $this->assertSame($secondPhoto->foto, $wahana->fotos->first()->foto);
        Storage::disk('public')->assertMissing($oldPhotos->first());
        Storage::disk('public')->assertExists($secondPhoto->foto);
        Storage::disk('public')->assertExists($wahana->fotos->last()->foto);

        $this->actingAs($admin)
            ->post(route('dashboard.cms.wahana.update', $wahana), [
                ...$this->validData(['is_unggulan' => false]),
                '_method' => 'patch',
                'existing_photo_order' => [],
            ])
            ->assertSessionHasErrors([
                'fotos' => 'Minimal satu foto harus tersedia untuk setiap Wahana.',
            ]);

        $this->assertCount(2, $wahana->fresh()->fotos);

        $this->actingAs($admin)
            ->patch(route('dashboard.cms.wahana.status', $wahana))
            ->assertRedirect(route('dashboard.cms.wahana.index'));
        $this->assertFalse($wahana->fresh()->is_active);

        $newPhotos = $wahana->fotos->pluck('foto');
        $this->actingAs($admin)
            ->delete(route('dashboard.cms.wahana.destroy', $wahana))
            ->assertRedirect(route('dashboard.cms.wahana.index'));
        $this->assertDatabaseMissing('wahana', ['id' => $wahana->id]);
        $this->assertDatabaseMissing('wahana_foto', ['wahana_id' => $wahana->id]);
        $newPhotos->each(fn (string $path) => Storage::disk('public')->assertMissing($path));
    }

    public function test_validation_only_accepts_official_labels_and_enforces_three_active_featured_wahana(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('dashboard.cms.wahana.store'), [
                ...$this->validData(),
                'fotos' => [$this->fakeImage('invalid-label.png')],
                'label' => ['Air', 'Label Buatan'],
            ])
            ->assertSessionHasErrors('label.1');

        foreach (range(1, 3) as $index) {
            $this->createWahana($admin, "Unggulan {$index}", true, true, $index);
        }

        $this->actingAs($admin)
            ->post(route('dashboard.cms.wahana.store'), [
                ...$this->validData(['nama_wahana' => 'Unggulan Keempat']),
                'fotos' => [$this->fakeImage('fourth.png')],
            ])
            ->assertSessionHasErrors([
                'is_unggulan' => 'Maksimal 3 Wahana dapat ditampilkan sebagai Wahana Unggulan.',
            ]);

        $this->assertDatabaseCount('wahana', 3);
    }

    public function test_public_pages_only_receive_active_wahana_in_display_order_with_public_photo_urls(): void
    {
        $admin = $this->userWithRole('admin');
        $this->createWahana($admin, 'Urutan Dua', true, true, 20, 'Air');
        $ordered = $this->createWahana($admin, 'Urutan Satu', true, true, 10, 'Darat,Anak-anak');
        WahanaFoto::create(['wahana_id' => $ordered->id, 'foto' => 'wahana/urutan-satu-detail.jpg', 'urutan' => 20]);
        WahanaFoto::create(['wahana_id' => $ordered->id, 'foto' => 'wahana/urutan-satu-cover.jpg', 'urutan' => 5]);
        $this->createWahana($admin, 'Biasa', true, false, 5, 'Santai');
        $this->createWahana($admin, 'Nonaktif', false, true, 0, 'Dewasa');

        $this->get(route('wahana'))->assertInertia(fn (Assert $page) => $page
            ->component('Wahana')
            ->where('wahanaFallbackEnabled', false)
            ->has('photos', 3)
            ->where('photos.0.title', 'Biasa')
            ->where('photos.1.title', 'Urutan Satu')
            ->where('photos.2.title', 'Urutan Dua')
            ->where('photos.1.cover_url', '/storage/wahana/urutan-satu-cover.jpg')
            ->has('photos.1.photos', 2)
            ->where('photos.1.photos.0.url', '/storage/wahana/urutan-satu-cover.jpg')
            ->where('photos.1.photos.1.url', '/storage/wahana/urutan-satu-detail.jpg')
            ->missing('photos.1.photo_path')
            ->where('categories.0.labels', fn ($labels) => collect($labels)->doesntContain('name', 'Dewasa')));

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('featuredRideFallbackEnabled', false)
            ->has('featuredRides', 2)
            ->where('featuredRides.0.title', 'Urutan Satu')
            ->where('featuredRides.0.cover_url', '/storage/wahana/urutan-satu-cover.jpg')
            ->where('featuredRides.1.title', 'Urutan Dua')
            ->missing('featuredRides.0.photo_path'));
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
            'nama_wahana' => 'Flying Fox',
            'deskripsi_singkat' => 'Wahana pengujian yang aman dan menyenangkan.',
            'label' => ['Darat', 'Adrenaline'],
            'is_active' => true,
            'is_unggulan' => true,
            'urutan_tampil' => 1,
            ...$overrides,
        ];
    }

    private function createWahana(
        User $creator,
        string $name,
        bool $isActive,
        bool $isFeatured,
        int $order,
        string $labels = 'Air',
    ): Wahana {
        return Wahana::create([
            'created_by' => $creator->id,
            'updated_by' => null,
            'nama_wahana' => $name,
            'deskripsi_singkat' => 'Deskripsi Wahana pengujian.',
            'foto' => 'wahana/'.str($name)->slug().'.jpg',
            'label' => $labels,
            'is_active' => $isActive,
            'is_unggulan' => $isFeatured,
            'urutan_tampil' => $order,
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
