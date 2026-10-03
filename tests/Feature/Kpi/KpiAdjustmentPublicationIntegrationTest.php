<?php

namespace Tests\Feature\Kpi;

use App\Models\KpiFinalScore;
use App\Models\KpiIndividualScore;
use App\Models\KpiMonthly;
use App\Models\KpiOpsItem;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\KpiSignature;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KpiAdjustmentPublicationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mpa_adjustment_is_gated_until_monthly_has_four_signatures(): void
    {
        Storage::fake('local');
        $employee = $this->actor('user', 'adjust-employee');
        $supervisor = $this->actor('admin', 'adjust-supervisor');
        $second = $this->actor('admin', 'adjust-second');
        $admin = $this->actor('super_admin', 'adjust-sa');
        $period = KpiPeriod::create(['bulan' => 10, 'tahun' => 2026, 'status' => 'active']);
        $participant = KpiParticipant::create([
            'kpi_period_id' => $period->id, 'karyawan_id' => $employee->karyawan_id,
            'jabatan_id' => $employee->karyawan->jabatan_id, 'jabatan_snapshot' => 'Pengujian',
            'atasan_langsung_id' => $supervisor->karyawan_id, 'atasan_kedua_id' => $second->karyawan_id,
            'status' => 'active',
        ]);
        $ki = KpiIndividualScore::create(['kpi_participant_id' => $participant->id, 'status' => 'not_filled', 'score' => 0]);
        $ops = KpiOpsItem::create([
            'kpi_participant_id' => $participant->id, 'urutan' => 1, 'kpi_item' => 'Item',
            'target_unit' => 1, 'target_bulanan' => 1, 'beban_target' => 1,
            'sumber_data_snapshot' => 'Uji', 'status' => 'not_filled', 'nilai_item' => 0,
        ]);
        $monthly = KpiMonthly::create([
            'kpi_participant_id' => $participant->id, 'status' => 'completed',
            'completed_at' => now(), 'mpa_score' => 4, 'attendance_score' => 4,
            'reward_punishment_score' => 3,
        ]);
        KpiSignature::create(['signable_type' => KpiMonthly::class, 'signable_id' => $monthly->id, 'role' => 'hrd_publish', 'source' => 'automatic', 'signed_for_user_id' => $admin->id, 'signed_by_user_id' => $admin->id]);

        $this->actingAs($admin)->post(route('dashboard.kpi.correct'), [
            'period_id' => $period->id, 'participant_id' => $participant->id,
            'component' => 'mpa', 'reason' => 'Adjustment test',
            'payload' => ['kinerja_operasional' => 10, 'sikap_kerja' => 10, 'team_work' => 10, 'inisiatif' => 10, 'performance' => 'x', 'coaching' => 'x'],
        ])->assertRedirect();
        $this->assertSame(0.0, (float) KpiFinalScore::where('kpi_participant_id', $participant->id)->value('reward_punishment_score'));

        foreach ([[$employee, 'employee'], [$supervisor, 'atasan_langsung'], [$second, 'atasan_kedua']] as [$actor, $slot]) {
            $this->actingAs($actor)->post(route('dashboard.kpi.sign'), ['signable_type' => 'monthly', 'signable_id' => $monthly->id, 'signature_slot' => $slot])->assertRedirect();
        }
        $this->actingAs($admin)->get(route('dashboard.kpi.final', ['period' => $period->id]));
        $this->assertSame(3.0, (float) KpiFinalScore::where('kpi_participant_id', $participant->id)->value('reward_punishment_score'));
    }

    private function actor(string $roleName, string $name): User
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => $roleName])->id]);
        $user->karyawan->update(['nama' => $name, 'foto_tanda_tangan' => 'karyawan/'.$name.'.png']);
        Storage::disk('local')->put('karyawan/'.$name.'.png', $name);
        return $user->refresh();
    }
}
