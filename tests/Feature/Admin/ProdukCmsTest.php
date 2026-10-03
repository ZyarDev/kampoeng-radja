<?php

namespace Tests\Feature\Admin;

use App\Models\Produk;
use App\Models\Penempatan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProdukCmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_and_super_admin_can_manage_products(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('dashboard.cms.home'))->assertOk();
        $this->actingAs($this->userWithRole('super_admin'))->get(route('dashboard.cms.home'))->assertOk();
        $this->actingAs($this->userWithRole('user'))->post(route('dashboard.cms.home.products.store'), [])->assertForbidden();
    }

    public function test_admin_can_create_product_with_both_images(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('dashboard.cms.home.products.store'), [
            ...$this->validPayload(),
            'thumbnail' => $this->fakeImage('thumbnail.png'),
            'hero_image' => $this->fakeImage('hero.png'),
        ])->assertRedirect(route('dashboard.cms.home'));

        $produk = Produk::query()->sole();
        $this->assertSame($admin->id, $produk->created_by);
        Storage::disk('public')->assertExists($produk->thumbnail);
        Storage::disk('public')->assertExists($produk->hero_image);
    }

    public function test_product_validation_enforces_limits_and_images(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('admin'))->post(route('dashboard.cms.home.products.store'), [
            'nama' => str_repeat('n', 151),
            'deskripsi_singkat' => str_repeat('s', 251),
            'deskripsi_lengkap' => str_repeat('l', 2001),
            'thumbnail' => UploadedFile::fake()->create('thumbnail.pdf', 10, 'application/pdf'),
            'hero_image' => UploadedFile::fake()->create('hero.pdf', 10, 'application/pdf'),
            'is_active' => true,
            'urutan_tampil' => 1000,
        ])->assertSessionHasErrors(['nama', 'deskripsi_singkat', 'deskripsi_lengkap', 'thumbnail', 'hero_image', 'urutan_tampil']);
    }

    public function test_update_preserves_images_then_replaces_each_safely(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $produk = $this->createProduct($admin, 'products/old-thumb.jpg', 'products/old-hero.jpg');
        Storage::disk('public')->put($produk->thumbnail, 'thumb');
        Storage::disk('public')->put($produk->hero_image, 'hero');

        $this->actingAs($admin)->patch(route('dashboard.cms.home.products.update', $produk), $this->validPayload(['nama' => 'Produk Diperbarui']))
            ->assertRedirect(route('dashboard.cms.home'));
        $this->assertSame('products/old-thumb.jpg', $produk->fresh()->thumbnail);
        $this->assertSame('products/old-hero.jpg', $produk->fresh()->hero_image);

        $this->actingAs($admin)->post(route('dashboard.cms.home.products.update', $produk), [
            ...$this->validPayload(), '_method' => 'patch',
            'thumbnail' => $this->fakeImage('new-thumb.png'),
            'hero_image' => $this->fakeImage('new-hero.png'),
        ])->assertRedirect(route('dashboard.cms.home'));

        $produk->refresh();
        Storage::disk('public')->assertExists($produk->thumbnail);
        Storage::disk('public')->assertExists($produk->hero_image);
        Storage::disk('public')->assertMissing('products/old-thumb.jpg');
        Storage::disk('public')->assertMissing('products/old-hero.jpg');
    }

    public function test_delete_cleans_product_assets(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $produk = $this->createProduct($admin, 'products/thumb.jpg', 'products/hero.jpg');
        Storage::disk('public')->put($produk->thumbnail, 'thumb');
        Storage::disk('public')->put($produk->hero_image, 'hero');

        $this->actingAs($admin)->delete(route('dashboard.cms.home.products.destroy', $produk))->assertRedirect(route('dashboard.cms.home'));
        $this->assertDatabaseMissing('produk', ['id' => $produk->id]);
        Storage::disk('public')->assertMissing('products/thumb.jpg');
        Storage::disk('public')->assertMissing('products/hero.jpg');
    }

    public function test_home_only_exposes_active_products_in_display_order(): void
    {
        $admin = $this->userWithRole('admin');
        $second = $this->createProduct($admin, 'products/second.jpg', 'products/second-hero.jpg', ['nama' => 'Kedua', 'urutan_tampil' => 2]);
        $first = $this->createProduct($admin, 'products/first.jpg', 'products/first-hero.jpg', ['nama' => 'Pertama', 'urutan_tampil' => 1]);
        $this->createProduct($admin, 'products/hidden.jpg', 'products/hidden-hero.jpg', ['nama' => 'Tersembunyi', 'is_active' => false, 'urutan_tampil' => 0]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->component('Home')->has('products', 2)
            ->where('products.0.id', $first->id)->where('products.1.id', $second->id)
            ->where('products.0.thumbnail', '/storage/products/first.jpg')
            ->where('products.0.heroImage', '/storage/products/first-hero.jpg'));
    }

    private function validPayload(array $overrides = []): array
    {
        return [...[
            'nama' => 'Outing Class', 'deskripsi_singkat' => 'Paket kegiatan edukatif.',
            'deskripsi_lengkap' => 'Deskripsi lengkap Produk.', 'is_active' => true, 'urutan_tampil' => 1,
        ], ...$overrides];
    }

    private function createProduct(User $user, string $thumbnail, string $hero, array $overrides = []): Produk
    {
        return Produk::create([...$this->validPayload(), 'thumbnail' => $thumbnail, 'hero_image' => $hero, 'created_by' => $user->id, ...$overrides]);
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
