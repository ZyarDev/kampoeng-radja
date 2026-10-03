<?php

namespace Tests\Feature\Kpi;

use App\Models\KpiIndividualScore;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\KpiSignature;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KpiSignatureAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_sign_only_the_employee_slot(): void
    {
        Storage::fake('local');
        [$employee, $supervisor, $score] = $this->scoreFixture();
        $employee->karyawan->update(['foto_tanda_tangan' => 'karyawan/employee.png']);
        Storage::disk('local')->put('karyawan/employee.png', 'employee');

        $this->actingAs($employee)->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'kinerja_individu',
            'signable_id' => $score->id,
            'signature_slot' => 'employee',
        ])->assertRedirect();

        $this->assertDatabaseHas('kpi_signatures', [
            'signable_id' => $score->id,
            'role' => 'employee',
            'signed_for_user_id' => $employee->id,
            'signed_by_user_id' => $employee->id,
        ]);

        $this->actingAs($employee)->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'kinerja_individu',
            'signable_id' => $score->id,
            'signature_slot' => 'atasan_langsung',
        ])->assertForbidden();

        $this->assertDatabaseMissing('kpi_signatures', [
            'signable_id' => $score->id,
            'role' => 'atasan_langsung',
        ]);
    }

    public function test_supervisor_cannot_forge_employee_slot_and_legacy_role_fallback_is_authorized(): void
    {
        Storage::fake('local');
        [$employee, $supervisor, $score] = $this->scoreFixture();
        $employee->karyawan->update(['foto_tanda_tangan' => 'karyawan/employee.png']);
        $supervisor->karyawan->update(['foto_tanda_tangan' => 'karyawan/supervisor.png']);
        Storage::disk('local')->put('karyawan/employee.png', 'employee');
        Storage::disk('local')->put('karyawan/supervisor.png', 'supervisor');
        KpiSignature::create([
            'signable_type' => KpiIndividualScore::class,
            'signable_id' => $score->id,
            'role' => 'employee',
            'source' => 'manual',
            'signed_for_user_id' => $employee->id,
            'signed_by_user_id' => $employee->id,
        ]);

        $this->actingAs($supervisor)->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'kinerja_individu',
            'signable_id' => $score->id,
            'signature_slot' => 'employee',
        ])->assertForbidden();

        $this->actingAs($supervisor)->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'kinerja_individu',
            'signable_id' => $score->id,
            'role' => 'atasan_langsung',
        ])->assertRedirect();

        $this->assertDatabaseHas('kpi_signatures', [
            'signable_id' => $score->id,
            'role' => 'atasan_langsung',
            'signed_for_user_id' => $supervisor->id,
            'signed_by_user_id' => $supervisor->id,
            'source' => 'direct_supervisor',
        ]);
    }

    public function test_unrelated_user_cannot_sign_a_supervisor_slot(): void
    {
        Storage::fake('local');
        [$employee, , $score] = $this->scoreFixture();
        $unrelated = User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => 'user'])->id]);
        $unrelated->karyawan->update(['foto_tanda_tangan' => 'karyawan/unrelated.png']);
        Storage::disk('local')->put('karyawan/unrelated.png', 'unrelated');

        $this->actingAs($unrelated)->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'kinerja_individu',
            'signable_id' => $score->id,
            'signature_slot' => 'atasan_langsung',
        ])->assertForbidden();
    }

    public function test_protected_signature_route_requires_scope_and_returns_404_when_missing(): void
    {
        Storage::fake('local');
        [$employee, $supervisor, $score] = $this->scoreFixture();
        $signature = KpiSignature::create([
            'signable_type' => KpiIndividualScore::class,
            'signable_id' => $score->id,
            'role' => 'employee',
            'source' => 'manual',
            'signed_for_user_id' => $employee->id,
            'signed_by_user_id' => $employee->id,
            'signature_path' => 'kpi/signatures/individual/test.png',
        ]);
        Storage::disk('local')->put($signature->signature_path, 'signature');

        $this->actingAs($employee)->get(route('dashboard.kpi.signature.file', $signature))->assertOk();
        $this->actingAs($supervisor)->get(route('dashboard.kpi.signature.file', $signature))->assertOk();

        Storage::disk('local')->delete($signature->signature_path);
        $this->actingAs($employee)->get(route('dashboard.kpi.signature.file', $signature))->assertNotFound();
    }

    /** @return array{0: User, 1: User, 2: KpiIndividualScore} */
    private function scoreFixture(): array
    {
        $employee = User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => 'user'])->id]);
        $supervisor = User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => 'admin'])->id]);
        $period = KpiPeriod::create(['bulan' => 10, 'tahun' => 2026, 'status' => 'active']);
        $participant = KpiParticipant::create([
            'kpi_period_id' => $period->id,
            'karyawan_id' => $employee->karyawan_id,
            'jabatan_id' => $employee->karyawan->jabatan_id,
            'jabatan_snapshot' => 'Karyawan',
            'atasan_langsung_id' => $supervisor->karyawan_id,
            'atasan_langsung_snapshot' => 'Supervisor',
            'status' => 'active',
        ]);
        $score = KpiIndividualScore::create([
            'kpi_participant_id' => $participant->id,
            'score' => 80,
            'status' => 'submitted',
            'value_locked' => true,
        ]);

        return [$employee, $supervisor, $score];
    }
}
