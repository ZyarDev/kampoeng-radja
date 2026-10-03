<?php

namespace Tests\Feature\Kpi;

use App\Models\KpiDailyReport;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KpiDailyIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_daily_report_exposes_participant_nik_and_supervisor_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));
        $employee = User::factory()->create([
            'role_id' => Role::firstOrCreate(['nama_role' => 'user'])->id,
        ]);
        $supervisor = User::factory()->create([
            'role_id' => Role::firstOrCreate(['nama_role' => 'admin'])->id,
        ]);
        $employee->karyawan->update(['nik' => 'NIK-DAILY-001', 'nama' => 'Karyawan Daily']);
        $supervisor->karyawan->update(['nama' => 'Atasan Snapshot']);
        $period = KpiPeriod::create(['bulan' => 10, 'tahun' => 2026, 'status' => 'active']);
        KpiParticipant::create([
            'kpi_period_id' => $period->id,
            'karyawan_id' => $employee->karyawan_id,
            'jabatan_id' => $employee->karyawan->jabatan_id,
            'jabatan_snapshot' => 'Jabatan Snapshot',
            'departemen_snapshot' => 'Departemen Snapshot',
            'penempatan_snapshot' => 'Penempatan Snapshot',
            'atasan_langsung_id' => $supervisor->karyawan_id,
            'atasan_langsung_snapshot' => 'Atasan Snapshot',
            'status' => 'active',
        ]);
        // A legacy report can have no report-level supervisor snapshot. The
        // participant snapshot must still be used for the identity header.
        KpiDailyReport::create([
            'karyawan_id' => $employee->karyawan_id,
            'tanggal' => '2026-10-01',
            'status' => 'draft',
            'atasan_snapshot_id' => null,
        ]);

        $this->actingAs($employee)
            ->get(route('dashboard.kpi.daily', ['period_id' => $period->id, 'tanggal' => '2026-10-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Internal/Kpi/DailyReport')
                ->where('employeeHeader.nik', 'NIK-DAILY-001')
                ->where('employeeHeader.atasan_langsung', 'Atasan Snapshot')
            );
    }
}
