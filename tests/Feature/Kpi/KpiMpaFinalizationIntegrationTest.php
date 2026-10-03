<?php

namespace Tests\Feature\Kpi;

use App\Models\KpiMonthly;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\KpiSignature;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KpiMpaFinalizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalization_is_scoped_to_ready_participant(): void
    {
        Storage::fake('local');
        $admin = $this->actor('super_admin', 'finalizer');
        $period = KpiPeriod::create(['bulan' => 10, 'tahun' => 2026, 'status' => 'active']);
        $ready = $this->participant($period, 'ready');
        $pending = $this->participant($period, 'pending');
        KpiMonthly::create([
            'kpi_participant_id' => $ready->id,
            'status' => 'evaluator_completed',
            'attendance_score' => 5,
            'reward_punishment_score' => 2,
            'hrd_initial_completed_at' => now(),
            'hrd_initial_completed_by' => $admin->id,
            'evaluator_completed_at' => now(),
            'evaluator_completed_by' => $admin->id,
        ]);
        KpiMonthly::create([
            'kpi_participant_id' => $pending->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($admin)->post(route('dashboard.kpi.mpa', $period), [
            'action' => 'finalize',
            'karyawan_id' => $ready->karyawan_id,
        ])->assertRedirect();

        $this->assertDatabaseHas('kpi_monthlies', [
            'kpi_participant_id' => $ready->id,
            'hrd_finalized_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('kpi_signatures', [
            'signable_type' => KpiMonthly::class,
            'signable_id' => KpiMonthly::where('kpi_participant_id', $ready->id)->value('id'),
            'role' => 'hrd_publish',
            'signed_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('kpi_monthlies', [
            'kpi_participant_id' => $pending->id,
            'hrd_finalized_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('kpi_monthlies', [
            'kpi_participant_id' => $pending->id,
            'status' => 'scheduled',
        ]);
    }

    private function participant(KpiPeriod $period, string $name): KpiParticipant
    {
        $user = $this->actor('user', $name);
        return KpiParticipant::create([
            'kpi_period_id' => $period->id,
            'karyawan_id' => $user->karyawan_id,
            'jabatan_id' => $user->karyawan->jabatan_id,
            'jabatan_snapshot' => 'Pengujian',
            'status' => 'active',
        ]);
    }

    private function actor(string $roleName, string $name): User
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => $roleName])->id]);
        $user->karyawan->update(['nama' => $name, 'foto_tanda_tangan' => 'karyawan/'.$name.'.png']);
        Storage::disk('local')->put('karyawan/'.$name.'.png', $name);
        return $user->refresh();
    }
}
