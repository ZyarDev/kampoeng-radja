<?php

namespace Tests\Feature\Admin;

use App\Models\EventPromo;
use App\Models\Penempatan;
use App\Models\Role;
use App\Models\User;
use App\Support\WhatsAppNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventPromoCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_event_promo_crud(): void
    {
        $this->get('/admin/event-promo')->assertRedirect(route('login'));
        $this->get('/admin/event-promo/create')->assertRedirect(route('login'));
        $this->post('/admin/event-promo', [])->assertRedirect(route('login'));
        $this->get('/admin/event-promo/1/edit')->assertRedirect(route('login'));
        $this->patch('/admin/event-promo/1', [])->assertRedirect(route('login'));
        $this->delete('/admin/event-promo/1')->assertRedirect(route('login'));
    }

    public function test_user_role_cannot_access_event_promo_crud(): void
    {
        $user = $this->userWithRole('user');
        $item = $this->createEventPromo($user, 'event-promo/forbidden.jpg');

        $this->actingAs($user)->get('/admin/event-promo')->assertForbidden();
        $this->actingAs($user)->get('/admin/event-promo/create')->assertForbidden();
        $this->actingAs($user)->post('/admin/event-promo', [])->assertForbidden();
        $this->actingAs($user)->get(route('admin.event-promo.edit', $item))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.event-promo.update', $item), [])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.event-promo.destroy', $item))->assertForbidden();
    }

    public function test_admin_and_super_admin_can_open_event_promo_index(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/event-promo')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/EventPromo/Index')
                ->has('items'));

        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/admin/event-promo')
            ->assertInertia(fn (Assert $page) => $page->component('Admin/EventPromo/Index'));
    }

    public function test_admin_can_create_event_promo_with_poster_and_server_side_audit(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post('/admin/event-promo', [
            ...$this->validPromoData(),
            'poster' => $this->fakeImage('poster.png'),
            'link_wa' => null,
            'created_by' => 999999,
            'updated_by' => 999999,
        ])->assertRedirect(route('admin.event-promo.index'));

        $item = EventPromo::query()->sole();
        $this->assertSame($admin->id, $item->created_by);
        $this->assertNull($item->updated_by);
        $this->assertNull($item->link_wa);
        $this->assertStringStartsWith('event-promo/', $item->poster);
        Storage::disk('public')->assertExists($item->poster);
    }

    public function test_create_validation_rejects_invalid_fields_and_poster(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/event-promo', [
                'judul' => str_repeat('a', 151),
                'deskripsi_singkat' => str_repeat('b', 256),
                'deskripsi_lengkap' => '',
                'poster' => UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'),
                'tanggal_mulai' => '2026-08-20',
                'tanggal_selesai' => '2026-08-19',
                'link_wa' => str_repeat('c', 256),
                'is_active' => 'invalid',
                'urutan_tampil' => -1,
            ])
            ->assertSessionHasErrors(['judul', 'deskripsi_singkat', 'deskripsi_lengkap', 'poster', 'tanggal_selesai', 'link_wa', 'is_active', 'urutan_tampil']);

        $this->assertDatabaseCount('event_promo', 0);
    }

    public function test_admin_can_update_without_replacing_poster_and_sets_updated_by(): void
    {
        Storage::fake('public');
        $creator = $this->userWithRole('super_admin');
        $admin = $this->userWithRole('admin');
        $item = $this->createEventPromo($creator, 'event-promo/original.jpg');
        Storage::disk('public')->put($item->poster, 'original');

        $this->actingAs($admin)->patch(route('admin.event-promo.update', $item), [
            ...$this->validPromoData([
                'judul' => 'Judul Diperbarui',
                'deskripsi_singkat' => 'Deskripsi singkat diperbarui.',
                'link_wa' => 'https://wa.me/628123456789',
            ]),
        ])->assertRedirect(route('admin.event-promo.index'));

        $item->refresh();
        $this->assertSame('Judul Diperbarui', $item->judul);
        $this->assertSame($creator->id, $item->created_by);
        $this->assertSame($admin->id, $item->updated_by);
        $this->assertSame('628123456789', $item->link_wa);
        $this->assertSame('event-promo/original.jpg', $item->poster);
        Storage::disk('public')->assertExists($item->poster);
    }

    public function test_replacing_poster_removes_only_the_unused_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $item = $this->createEventPromo($admin, 'event-promo/old.jpg');
        Storage::disk('public')->put($item->poster, 'old');

        $this->actingAs($admin)->post(route('admin.event-promo.update', $item), [
            '_method' => 'patch',
            ...$this->validPromoData([
                'judul' => $item->judul,
                'deskripsi_singkat' => $item->deskripsi_singkat,
            ]),
            'poster' => $this->fakeImage('replacement.png'),
            'link_wa' => '',
        ])->assertRedirect(route('admin.event-promo.index'));

        $newPath = $item->fresh()->poster;
        $this->assertNotSame('event-promo/old.jpg', $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing('event-promo/old.jpg');
    }

    public function test_delete_removes_record_and_unused_poster(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $item = $this->createEventPromo($admin, 'event-promo/delete-me.jpg');
        Storage::disk('public')->put($item->poster, 'poster');

        $this->actingAs($admin)
            ->delete(route('admin.event-promo.destroy', $item))
            ->assertRedirect(route('admin.event-promo.index'));

        $this->assertDatabaseMissing('event_promo', ['id' => $item->id]);
        Storage::disk('public')->assertMissing('event-promo/delete-me.jpg');
    }

    public function test_delete_preserves_poster_still_used_by_another_record(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $first = $this->createEventPromo($admin, 'event-promo/shared.jpg');
        $this->createEventPromo($admin, 'event-promo/shared.jpg', 'Promo Kedua');
        Storage::disk('public')->put('event-promo/shared.jpg', 'shared');

        $this->actingAs($admin)->delete(route('admin.event-promo.destroy', $first));

        Storage::disk('public')->assertExists('event-promo/shared.jpg');
    }

    public function test_home_exposes_cms_promotions_with_one_public_poster_url(): void
    {
        $creator = $this->userWithRole('admin');
        $promo = $this->createEventPromo(
            $creator,
            'event-promo/promo.jpg',
            'Promo dari CMS',
            'https://wa.me/628123456789',
        );

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('promotions', 1)
            ->where('promotions.0.title', 'Promo dari CMS')
            ->where('promotions.0.description', 'Deskripsi singkat promo pengujian.')
            ->where('promotions.0.detail', 'Deskripsi lengkap promo pengujian.')
            ->where('promotions.0.period', $promo->periodLabel())
            ->where('promotions.0.poster_url', '/storage/event-promo/promo.jpg')
            ->where('promotions.0.link_wa', 'https://wa.me/628123456789')
            ->missing('promotions.0.poster')
            ->missing('promotions.0.image'));
    }

    public function test_home_returns_empty_promotions_for_the_frontend_fallback(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('promotions', 0)
            ->where('promotionFallbackEnabled', true));
    }

    public function test_cms_home_is_available_to_admin_and_super_admin_but_not_user(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('dashboard.cms.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Internal/CMS/Home/Index')
                ->has('promotions')
                ->has('promoSummary'));

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('dashboard.cms.home'))
            ->assertOk();

        $this->actingAs($this->userWithRole('user'))
            ->get(route('dashboard.cms.home'))
            ->assertForbidden();
    }

    public function test_cms_home_can_create_and_toggle_a_promo_using_the_shared_controller(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('dashboard.cms.home.promo.store'), [
                ...$this->validPromoData(),
                'poster' => $this->fakeImage('cms-promo.png'),
            ])
            ->assertRedirect(route('dashboard.cms.home'));

        $promo = EventPromo::query()->sole();
        $this->assertTrue($promo->is_active);

        $this->actingAs($admin)
            ->patch(route('dashboard.cms.home.promo.status', $promo))
            ->assertRedirect(route('dashboard.cms.home'));

        $this->assertFalse($promo->fresh()->is_active);
        $this->assertSame($admin->id, $promo->fresh()->updated_by);
    }

    public function test_public_home_only_exposes_active_promotions_in_the_current_period_in_display_order(): void
    {
        $creator = $this->userWithRole('admin');
        $today = now('Asia/Jakarta')->toImmutable();

        $second = $this->createEventPromo($creator, 'event-promo/second.jpg', 'Urutan Kedua');
        $second->update(['urutan_tampil' => 20]);
        $first = $this->createEventPromo($creator, 'event-promo/first.jpg', 'Urutan Pertama');
        $first->update(['urutan_tampil' => 10]);
        $this->createEventPromo($creator, 'event-promo/scheduled.jpg', 'Terjadwal')->update([
            'tanggal_mulai' => $today->addDay()->toDateString(),
            'tanggal_selesai' => $today->addDays(2)->toDateString(),
        ]);
        $this->createEventPromo($creator, 'event-promo/expired.jpg', 'Berakhir')->update([
            'tanggal_mulai' => $today->subDays(2)->toDateString(),
            'tanggal_selesai' => $today->subDay()->toDateString(),
        ]);
        $this->createEventPromo($creator, 'event-promo/inactive.jpg', 'Nonaktif')->update(['is_active' => false]);
        EventPromo::create([
            'created_by' => $creator->id,
            'judul' => 'Data Lama Tanpa Periode',
            'deskripsi_singkat' => 'Tidak boleh tampil.',
            'poster' => 'event-promo/legacy.jpg',
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('promotions', 2)
            ->where('promotionFallbackEnabled', false)
            ->where('promotions.0.title', 'Urutan Pertama')
            ->where('promotions.1.title', 'Urutan Kedua'));
    }

    public function test_whatsapp_number_is_normalized_while_existing_url_is_presented_as_a_local_number(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $legacy = $this->createEventPromo(
            $admin,
            'event-promo/legacy-whatsapp.jpg',
            'Promo URL Lama',
            'https://wa.me/628111111111',
        );

        $this->actingAs($admin)
            ->get(route('dashboard.cms.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('promotions.0.link_wa', '08111111111')
                ->where('promotions.0.link_wa_url', 'https://wa.me/628111111111'));

        $this->actingAs($admin)
            ->post(route('dashboard.cms.home.promo.store'), [
                ...$this->validPromoData(['judul' => 'Promo Nomor Baru', 'link_wa' => '0812-3456-7890']),
                'poster' => $this->fakeImage('normalized-whatsapp.png'),
            ])
            ->assertRedirect(route('dashboard.cms.home'));

        $newPromo = EventPromo::query()->whereKeyNot($legacy->id)->sole();
        $this->assertSame('6281234567890', $newPromo->link_wa);

        $normalizer = app(WhatsAppNumber::class);
        $this->assertSame('6281234567890', $normalizer->normalize('+62 812 3456 7890'));
        $this->assertSame('6281234567890', $normalizer->normalize('6281234567890'));
        $this->assertSame('6281234567890', $normalizer->normalize('https://wa.me/6281234567890'));
        $this->assertSame('https://wa.me/6281234567890', $normalizer->toUrl($newPromo->link_wa));
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

    private function createEventPromo(
        User $creator,
        string $poster,
        string $title = 'Promo Pengujian',
        ?string $linkWa = null,
    ): EventPromo {
        return EventPromo::create([
            'created_by' => $creator->id,
            'updated_by' => null,
            'judul' => $title,
            'deskripsi_singkat' => 'Deskripsi singkat promo pengujian.',
            'deskripsi_lengkap' => 'Deskripsi lengkap promo pengujian.',
            'poster' => $poster,
            'tanggal_mulai' => now('Asia/Jakarta')->subDay()->toDateString(),
            'tanggal_selesai' => now('Asia/Jakarta')->addDay()->toDateString(),
            'link_wa' => $linkWa,
            'is_active' => true,
            'urutan_tampil' => 0,
        ]);
    }

    private function validPromoData(array $overrides = []): array
    {
        return [
            'judul' => 'Promo Pengujian',
            'deskripsi_singkat' => 'Deskripsi singkat promo pengujian.',
            'deskripsi_lengkap' => 'Deskripsi lengkap promo pengujian.',
            'tanggal_mulai' => now('Asia/Jakarta')->subDay()->toDateString(),
            'tanggal_selesai' => now('Asia/Jakarta')->addDay()->toDateString(),
            'link_wa' => null,
            'is_active' => true,
            'urutan_tampil' => 0,
            ...$overrides,
        ];
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
