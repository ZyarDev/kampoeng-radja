<?php

namespace Tests\Feature\Kpi;

use App\Models\KpiDailyReport;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KpiDailyApprovalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_direct_supervisor_approves_during_normal_window(): void
    {
        $fixture = $this->dailyFixture('2026-10-01 12:00:00');

        $this->actingAs($fixture['supervisor'])->post(route('dashboard.kpi.daily.approve', $fixture['report']))->assertRedirect();

        $this->assertDatabaseHas('kpi_daily_reports', [
            'id' => $fixture['report']->id,
            'approval_source' => 'direct_supervisor',
            'approved_by' => $fixture['supervisor']->id,
        ]);
    }

    public function test_super_admin_assists_before_deadline_and_takes_over_after_deadline(): void
    {
        $fixture = $this->dailyFixture('2026-10-01 12:00:00');

        $this->actingAs($fixture['superAdmin'])->post(route('dashboard.kpi.daily.approve', $fixture['report']))->assertRedirect();
        $this->assertDatabaseHas('kpi_daily_reports', ['id' => $fixture['report']->id, 'approval_source' => 'super_admin_assistance']);

        $fixture = $this->dailyFixture('2026-10-03 12:00:00');
        $this->actingAs($fixture['superAdmin'])->post(route('dashboard.kpi.daily.approve', $fixture['report']))->assertRedirect();
        $this->assertDatabaseHas('kpi_daily_reports', ['id' => $fixture['report']->id, 'approval_source' => 'super_admin_takeover']);
    }

    public function test_super_admin_who_is_snapshot_supervisor_keeps_direct_supervisor_source_in_normal_window(): void
    {
        $fixture = $this->dailyFixture('2026-10-01 12:00:00', true);

        $this->actingAs($fixture['superAdmin'])->post(route('dashboard.kpi.daily.approve', $fixture['report']))->assertRedirect();

        $this->assertDatabaseHas('kpi_daily_reports', [
            'id' => $fixture['report']->id,
            'approval_source' => 'direct_supervisor',
        ]);
    }

    private function dailyFixture(string $now, bool $supervisorIsSuperAdmin = false): array
    {
        Carbon::setTestNow(Carbon::parse($now, 'Asia/Jakarta'));
        Storage::fake('local');
        $employee = $this->actor('user', 'daily-employee');
        $supervisor = $this->actor($supervisorIsSuperAdmin ? 'super_admin' : 'admin', 'daily-supervisor');
        $superAdmin = $supervisorIsSuperAdmin ? $supervisor : $this->actor('super_admin', 'daily-sa');
        $employee->karyawan->update(['atasan_langsung_id' => $supervisor->karyawan_id]);
        $report = KpiDailyReport::create([
            'karyawan_id' => $employee->karyawan_id,
            'tanggal' => Carbon::parse('2026-10-01')->toDateString(),
            'atasan_snapshot_id' => $supervisor->karyawan_id,
            'status' => 'waiting_approval',
            'submitted_at' => Carbon::now(),
        ]);

        return compact('employee', 'supervisor', 'superAdmin', 'report');
    }

    private function actor(string $roleName, string $name): User
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => $roleName])->id]);
        $user->karyawan->update(['nama' => $name, 'foto_tanda_tangan' => 'karyawan/'.$name.'.png']);
        Storage::disk('local')->put('karyawan/'.$name.'.png', $name);
        return $user->refresh();
    }
}
