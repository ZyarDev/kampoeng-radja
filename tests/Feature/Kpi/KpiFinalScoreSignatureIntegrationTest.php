<?php

namespace Tests\Feature\Kpi;

use App\Models\KpiFinalScore;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\KpiSignature;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KpiFinalScoreSignatureIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_employee_and_supervisor_sign_only_their_backend_resolved_slots(): void
    {
        $fixture = $this->fixture('2026-11-05 12:00:00');

        $this->actingAs($fixture['employee'])->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'final_score', 'signable_id' => $fixture['final']->id,
            'signature_slot' => 'atasan_langsung',
        ])->assertRedirect();
        $this->actingAs($fixture['supervisor'])->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'final_score', 'signable_id' => $fixture['final']->id,
            'signature_slot' => 'employee',
        ])->assertRedirect();

        $this->assertDatabaseHas('kpi_signatures', [
            'signable_id' => $fixture['final']->id, 'role' => 'employee',
            'signed_for_user_id' => $fixture['employee']->id, 'signed_by_user_id' => $fixture['employee']->id,
        ]);
        $this->assertDatabaseHas('kpi_signatures', [
            'signable_id' => $fixture['final']->id, 'role' => 'atasan_langsung',
            'signed_for_user_id' => $fixture['supervisor']->id, 'signed_by_user_id' => $fixture['supervisor']->id,
            'source' => 'direct_supervisor',
        ]);
    }

    public function test_unrelated_actor_is_rejected_and_super_admin_source_follows_timing(): void
    {
        $fixture = $this->fixture('2026-11-05 12:00:00');
        $this->actingAs($fixture['unrelated'])->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'final_score', 'signable_id' => $fixture['final']->id,
            'signature_slot' => 'atasan_langsung',
        ])->assertForbidden();

        $this->actingAs($fixture['superAdmin'])->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'final_score', 'signable_id' => $fixture['final']->id,
            'signature_slot' => 'atasan_langsung',
        ])->assertRedirect();
        $this->assertDatabaseHas('kpi_signatures', [
            'signable_id' => $fixture['final']->id, 'role' => 'atasan_langsung',
            'signed_by_user_id' => $fixture['superAdmin']->id, 'source' => 'super_admin_assistance',
        ]);

        KpiSignature::query()->delete();
        Carbon::setTestNow(Carbon::parse('2026-11-09 00:00:01', 'Asia/Jakarta'));
        $this->actingAs($fixture['superAdmin'])->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'final_score', 'signable_id' => $fixture['final']->id,
            'signature_slot' => 'atasan_langsung',
        ])->assertRedirect();
        $this->assertDatabaseHas('kpi_signatures', [
            'signable_id' => $fixture['final']->id, 'role' => 'atasan_langsung',
            'signed_by_user_id' => $fixture['superAdmin']->id, 'source' => 'super_admin_takeover',
        ]);
    }

    private function fixture(string $now): array
    {
        Carbon::setTestNow(Carbon::parse($now, 'Asia/Jakarta'));
        Storage::fake('local');
        $employee = $this->actor('user', 'final-employee');
        $supervisor = $this->actor('admin', 'final-supervisor');
        $superAdmin = $this->actor('super_admin', 'final-sa');
        $unrelated = $this->actor('user', 'final-unrelated');
        $period = KpiPeriod::create(['bulan' => 10, 'tahun' => 2026, 'status' => 'active']);
        $participant = KpiParticipant::create([
            'kpi_period_id' => $period->id,
            'karyawan_id' => $employee->karyawan_id,
            'jabatan_id' => $employee->karyawan->jabatan_id,
            'jabatan_snapshot' => 'Pengujian',
            'atasan_langsung_id' => $supervisor->karyawan_id,
            'atasan_langsung_snapshot' => 'Supervisor',
            'status' => 'active',
        ]);
        $final = KpiFinalScore::create([
            'kpi_participant_id' => $participant->id,
            'kategori' => 'Reward',
            'status' => 'completed',
        ]);
        return compact('employee', 'supervisor', 'superAdmin', 'unrelated', 'final');
    }

    private function actor(string $roleName, string $name): User
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => $roleName])->id]);
        $user->karyawan->update(['nama' => $name, 'foto_tanda_tangan' => 'karyawan/'.$name.'.png']);
        Storage::disk('local')->put('karyawan/'.$name.'.png', $name);
        return $user->refresh();
    }
}
