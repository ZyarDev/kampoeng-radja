<?php

namespace Tests\Feature;

use App\Models\ClosingEvent;
use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\JenisEvent;
use App\Models\Lokasi;
use App\Models\Pic;
use App\Models\Penempatan;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ClosingEventMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClosingEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-18 09:00:00');
        $this->seed(ClosingEventMasterSeeder::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_guest_and_read_only_users_follow_closing_event_view_matrix(): void
    {
        $this->get(route('dashboard.closing-event.index'))->assertRedirect(route('login'));
        $event = $this->createEvent($this->actor('super_admin', 'Admin Sistem', 'Management'), '2026-08-20');

        $actor = $this->actor('user', 'Staff', 'Operasional');
        $this->actingAs($actor)->get(route('dashboard.closing-event.index'))->assertOk();
        $this->actingAs($actor)->get(route('dashboard.closing-event.show', $event))->assertOk();
        $this->actingAs($actor)->get(route('dashboard.closing-event.create'))->assertForbidden();
        $this->actingAs($actor)->post(route('dashboard.closing-event.store'), $this->validData())->assertForbidden();
        $this->actingAs($actor)->get(route('dashboard.closing-event.edit', $event))->assertForbidden();
        $this->actingAs($actor)->put(route('dashboard.closing-event.update', $event), $this->validData())->assertForbidden();
        $this->actingAs($actor)->delete(route('dashboard.closing-event.destroy', $event))->assertForbidden();
        $this->actingAs($actor)->get(route('dashboard.closing-event.export', ['bulan' => 8, 'tahun' => 2026]))->assertForbidden();
        $this->actingAs($actor)->get(route('dashboard.closing-event.master.index'))->assertForbidden();
    }

    public function test_admin_with_marketing_placement_can_update_but_not_create_or_delete(): void
    {
        foreach (['Marketing', 'Marcom'] as $placement) {
            $actor = $this->actor('admin', 'Supervisor', 'Operasional', $placement);
            $event = $this->createEvent($this->actor('super_admin', 'Admin Sistem', 'Management'), '2026-08-20');

            $this->actingAs($actor)->get(route('dashboard.closing-event.index'))->assertOk();
            $this->actingAs($actor)->post(route('dashboard.closing-event.store'), $this->validData())->assertForbidden();
            $this->actingAs($actor)->get(route('dashboard.closing-event.edit', $event))->assertOk();
            $this->actingAs($actor)->put(route('dashboard.closing-event.update', $event), $this->validData(['konsumen' => 'Diperbarui']))->assertRedirect();
            $this->actingAs($actor)->delete(route('dashboard.closing-event.destroy', $event))->assertForbidden();
            $this->actingAs($actor)->get(route('dashboard.closing-event.export', ['bulan' => 8, 'tahun' => 2026]))->assertOk();
            $this->actingAs($actor)->get(route('dashboard.closing-event.master.index'))->assertOk();
        }
    }

    public function test_access_matrix_and_shared_sidebar_capabilities_are_enforced(): void
    {
        $matrix = [
            [$this->actor('super_admin', 'Admin Sistem', 'Management'), [true, true, true, true, true], true],
            [$this->actor('user', 'Staff', 'Marketing'), [true, true, true, true, true], false],
            [$this->actor('user', 'Staff', 'Marcom'), [true, true, true, true, true], false],
            [$this->actor('admin', 'Staff', 'Operasional', 'Marketing'), [true, false, true, false, true], true],
            [$this->actor('admin', 'Staff', 'Operasional'), [true, false, false, false, false], false],
            [$this->actor('user', 'Staff', 'Operasional', 'Marketing'), [true, false, false, false, false], false],
        ];

        foreach ($matrix as [$actor, $capabilities, $canManageMaster]) {
            $response = $this->actingAs($actor)->get(route('dashboard.closing-event.index'));
            $response->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Internal/ClosingEvent/Index')
                ->where('permissions.canView', $capabilities[0])
                ->where('permissions.canCreate', $capabilities[1])
                ->where('permissions.canUpdate', $capabilities[2])
                ->where('permissions.canDelete', $capabilities[3])
                ->where('permissions.canExport', $capabilities[4])
                ->where('auth.closingEvent.canView', true)
                ->where('auth.closingEvent.canManageMaster', $canManageMaster));
        }
    }

    public function test_super_admin_can_create_update_and_delete_event_with_audit_and_multiple_locations(): void
    {
        $admin = $this->actor('super_admin', 'Admin Sistem', 'Management');
        $data = $this->validData(['lokasi_ids' => Lokasi::query()->limit(3)->pluck('id')->all()]);

        $response = $this->actingAs($admin)->post(route('dashboard.closing-event.store'), $data);
        $event = ClosingEvent::query()->firstOrFail();

        $response->assertRedirect(route('dashboard.closing-event.show', $event));
        $this->assertSame($admin->id, $event->created_by);
        $this->assertNull($event->updated_by);
        $this->assertCount(3, $event->lokasi);

        $manager = $this->actor('admin', 'Manajer', 'Operasional', 'Marketing');
        $updated = $this->validData([
            'konsumen' => 'Konsumen Diperbarui',
            'lokasi_ids' => Lokasi::query()->limit(2)->pluck('id')->all(),
        ]);
        $this->actingAs($manager)->put(route('dashboard.closing-event.update', $event), $updated)
            ->assertRedirect(route('dashboard.closing-event.show', $event));
        $event->refresh();
        $this->assertSame('Konsumen Diperbarui', $event->konsumen);
        $this->assertSame($manager->id, $event->updated_by);
        $this->assertCount(2, $event->lokasi);

        $this->actingAs($manager)->delete(route('dashboard.closing-event.destroy', $event))->assertForbidden();
        $this->actingAs($admin)->delete(route('dashboard.closing-event.destroy', $event))->assertRedirect(route('dashboard.closing-event.index'));
        $this->assertDatabaseMissing('closing_event', ['id' => $event->id]);
        $this->assertDatabaseMissing('closing_event_lokasi', ['closing_event_id' => $event->id]);
    }

    public function test_marketing_department_employee_has_full_data_access_but_read_only_master_access(): void
    {
        foreach (['Marketing', 'Marcom'] as $department) {
            $actor = $this->actor('user', 'Mitra', $department);
            $this->actingAs($actor)->post(route('dashboard.closing-event.store'), $this->validData())->assertRedirect();
            $event = ClosingEvent::query()->latest('id')->firstOrFail();

            $this->actingAs($actor)->get(route('dashboard.closing-event.show', $event))->assertOk();
            $this->actingAs($actor)->get(route('dashboard.closing-event.edit', $event))->assertOk();
            $this->actingAs($actor)->put(route('dashboard.closing-event.update', $event), $this->validData())->assertRedirect();
            $this->actingAs($actor)->get(route('dashboard.closing-event.export', ['bulan' => 8, 'tahun' => 2026]))->assertOk();
            $this->actingAs($actor)->delete(route('dashboard.closing-event.destroy', $event))->assertRedirect();
            $this->actingAs($actor)->get(route('dashboard.closing-event.master.index'))->assertOk();
            $this->actingAs($actor)->post(route('dashboard.closing-event.master.pic.store'), ['nama_pic' => "{$department} PIC"])->assertForbidden();
        }
    }

    public function test_admin_with_marketing_or_marcom_placement_can_manage_master_data(): void
    {
        foreach (['Marketing', 'Marcom'] as $placement) {
            $actor = $this->actor('admin', 'Staff', 'Operasional', $placement);
            $this->actingAs($actor)->get(route('dashboard.closing-event.master.index'))->assertOk();
            $this->actingAs($actor)->post(route('dashboard.closing-event.master.pic.store'), ['nama_pic' => "{$placement} MASTER"])->assertSessionHas('success');
            $pic = Pic::query()->where('nama_pic', "{$placement} MASTER")->firstOrFail();
            $this->actingAs($actor)->put(route('dashboard.closing-event.master.pic.update', $pic), ['nama_pic' => "{$placement} MASTER UPDATED"])->assertSessionHas('success');
            $this->actingAs($actor)->delete(route('dashboard.closing-event.master.pic.destroy', $pic))->assertSessionHas('success');
        }
    }

    public function test_validation_rejects_invalid_master_locations_and_numeric_boundaries(): void
    {
        $actor = $this->actor('super_admin', 'Admin Sistem', 'Management');
        $locationId = Lokasi::query()->value('id');

        $this->actingAs($actor)->post(route('dashboard.closing-event.store'), $this->validData([
            'pic_id' => 999999,
            'event_id' => 999999,
            'lokasi_ids' => [$locationId, $locationId, 999999],
            'jumlah_pengunjung' => 0,
            'harga_total' => -1,
            'kontak' => str_repeat('1', 21),
        ]))->assertSessionHasErrors([
            'pic_id', 'event_id', 'lokasi_ids.1', 'lokasi_ids.2',
            'jumlah_pengunjung', 'harga_total', 'kontak',
        ]);

        $this->actingAs($actor)->post(route('dashboard.closing-event.store'), $this->validData(['lokasi_ids' => []]))
            ->assertSessionHasErrors('lokasi_ids');
    }

    public function test_index_defaults_to_current_month_filters_by_event_date_and_sorts_ascending(): void
    {
        $actor = $this->actor('super_admin', 'Admin Sistem', 'Management');
        $august25 = $this->createEvent($actor, '2026-08-25');
        $august25->forceFill(['created_at' => '2026-07-01 00:00:00'])->save();
        $august03 = $this->createEvent($actor, '2026-08-03');
        $august17 = $this->createEvent($actor, '2026-08-17');
        $september = $this->createEvent($actor, '2026-09-10');
        $september->forceFill(['created_at' => '2026-08-01 00:00:00'])->save();

        $this->actingAs($actor)->get(route('dashboard.closing-event.index'))->assertInertia(fn (Assert $page) => $page
            ->where('filters.bulan', 8)->where('filters.tahun', 2026)
            ->where('events.data', fn ($items) => collect($items)->pluck('id')->all() === [$august03->id, $august17->id, $august25->id]));

        $this->actingAs($actor)->get(route('dashboard.closing-event.index', ['bulan' => 9, 'tahun' => 2026]))
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.id', $september->id));
    }

    public function test_index_marks_every_event_scheduled_for_today_as_ongoing(): void
    {
        $actor = $this->actor('super_admin', 'Admin Sistem', 'Management');
        $todayFirst = $this->createEvent($actor, '2026-08-18');
        $todaySecond = $this->createEvent($actor, '2026-08-18');
        $anotherDay = $this->createEvent($actor, '2026-08-19');

        $this->actingAs($actor)->get(route('dashboard.closing-event.index'))->assertInertia(function (Assert $page) use ($todayFirst, $todaySecond, $anotherDay): void {
            $page->where('events.data', function ($items) use ($todayFirst, $todaySecond, $anotherDay): bool {
                $events = collect($items)->keyBy('id');

                return $events->get($todayFirst->id)['isOngoing'] === true
                    && $events->get($todaySecond->id)['isOngoing'] === true
                    && $events->get($anotherDay->id)['isOngoing'] === false;
            });
        });
    }

    public function test_super_admin_can_manage_masters_and_used_master_delete_is_rejected(): void
    {
        $admin = $this->actor('super_admin', 'Admin Sistem', 'Management');
        $this->actingAs($admin)->post(route('dashboard.closing-event.master.pic.store'), ['nama_pic' => 'PIC BARU'])->assertSessionHas('success');
        $pic = Pic::query()->where('nama_pic', 'PIC BARU')->firstOrFail();
        $this->actingAs($admin)->post(route('dashboard.closing-event.master.pic.store'), ['nama_pic' => 'PIC BARU'])->assertSessionHasErrors('nama_pic');
        $this->actingAs($admin)->put(route('dashboard.closing-event.master.pic.update', $pic), ['nama_pic' => 'PIC UPDATE'])->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('dashboard.closing-event.master.pic.destroy', $pic))->assertSessionHas('success');

        $event = $this->createEvent($admin, '2026-08-20');
        $this->actingAs($admin)->delete(route('dashboard.closing-event.master.pic.destroy', $event->pic))
            ->assertSessionHas('error', 'Data tidak dapat dihapus karena masih digunakan pada Closing Event.');
        $this->actingAs($admin)->delete(route('dashboard.closing-event.master.jenis-event.destroy', $event->jenisEvent))
            ->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('dashboard.closing-event.master.lokasi.destroy', $event->lokasi->first()))
            ->assertSessionHas('error');
    }

    public function test_master_tables_use_five_rows_and_independent_paginators(): void
    {
        $admin = $this->actor('super_admin', 'Admin Sistem', 'Management');

        $this->actingAs($admin)->get(route('dashboard.closing-event.master.index', [
            'pic_page' => 2,
            'event_page' => 3,
            'lokasi_page' => 2,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('Internal/ClosingEvent/Masters')
            ->where('pic.per_page', 5)
            ->where('pic.current_page', 2)
            ->has('pic.data', 2)
            ->where('jenisEvent.per_page', 5)
            ->where('jenisEvent.current_page', 3)
            ->has('jenisEvent.data', 5)
            ->where('lokasi.per_page', 5)
            ->where('lokasi.current_page', 2)
            ->has('lokasi.data', 5));
    }

    public function test_master_seeder_is_idempotent_and_contains_final_counts(): void
    {
        $this->seed(ClosingEventMasterSeeder::class);

        $this->assertSame(7, Pic::query()->count());
        $this->assertSame(28, JenisEvent::query()->count());
        $this->assertSame(11, Lokasi::query()->count());
    }

    private function actor(string $role, string $position, string $department, ?string $placement = null): User
    {
        $user = User::factory()->create([
            'role_id' => Role::firstOrCreate(['nama_role' => $role])->id,
        ]);
        $user->karyawan->update([
            'jabatan_id' => Jabatan::firstOrCreate(['nama_jabatan' => $position])->id,
            'departemen_id' => Departemen::firstOrCreate(['nama_departemen' => $department])->id,
            'penempatan_id' => $placement === null
                ? $user->karyawan->penempatan_id
                : Penempatan::firstOrCreate(['nama_penempatan' => $placement])->id,
        ]);

        return $user->refresh();
    }

    private function validData(array $overrides = []): array
    {
        return [
            ...[
                'pic_id' => Pic::query()->value('id'),
                'event_id' => JenisEvent::query()->value('id'),
                'tanggal' => '2026-08-20',
                'konsumen' => 'PT Contoh Jambi',
                'kontak' => '081234567890',
                'jam_kedatangan' => '08:00',
                'lokasi_ids' => [Lokasi::query()->value('id')],
                'additional' => null,
                'konsumsi' => true,
                'jumlah_pengunjung' => 100,
                'harga_total' => 15000000,
                'panitia' => null,
            ],
            ...$overrides,
        ];
    }

    private function createEvent(User $actor, string $date): ClosingEvent
    {
        $event = ClosingEvent::create([
            ...collect($this->validData(['tanggal' => $date]))->except('lokasi_ids')->all(),
            'created_by' => $actor->id,
            'updated_by' => null,
        ]);
        $event->lokasi()->attach(Lokasi::query()->value('id'));

        return $event->refresh();
    }
}
