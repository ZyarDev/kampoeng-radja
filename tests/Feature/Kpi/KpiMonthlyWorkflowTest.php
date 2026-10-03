<?php

namespace Tests\Feature\Kpi;

use App\Models\KpiMonthly;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\KpiSignature;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KpiMonthlyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_completes_only_after_all_four_logical_slots_are_signed(): void
    {
        Storage::fake('local');
        $fixture = $this->monthlyFixture(true);
        $this->signMonthly($fixture['employee'], $fixture['monthly'], 'employee');
        $this->signMonthly($fixture['supervisor'], $fixture['monthly'], 'atasan_langsung');
        $this->signMonthly($fixture['second'], $fixture['monthly'], 'atasan_kedua');

        $this->assertSame(4, KpiSignature::where('signable_type', KpiMonthly::class)
            ->where('signable_id', $fixture['monthly']->id)->count());
        $this->assertDatabaseHas('kpi_signatures', [
            'signable_id' => $fixture['monthly']->id,
            'role' => 'hrd_publish',
        ]);
    }

    public function test_missing_second_supervisor_requires_explicit_super_admin_signature(): void
    {
        Storage::fake('local');
        $fixture = $this->monthlyFixture(false);
        $this->signMonthly($fixture['employee'], $fixture['monthly'], 'employee');
        $this->signMonthly($fixture['supervisor'], $fixture['monthly'], 'atasan_langsung');

        $this->assertSame(3, KpiSignature::where('signable_type', KpiMonthly::class)
            ->where('signable_id', $fixture['monthly']->id)->count());
        $this->assertDatabaseMissing('kpi_signatures', [
            'signable_id' => $fixture['monthly']->id,
            'role' => 'atasan_kedua',
        ]);

        $this->signMonthly($fixture['superAdmin'], $fixture['monthly'], 'atasan_kedua');

        $this->assertDatabaseHas('kpi_signatures', [
            'signable_id' => $fixture['monthly']->id,
            'role' => 'atasan_kedua',
            'signed_by_user_id' => $fixture['superAdmin']->id,
            'source' => 'super_admin_assistance',
        ]);
    }

    public function test_mpa_correction_keeps_existing_monthly_signatures(): void
    {
        Storage::fake('local');
        $fixture = $this->monthlyFixture(true);
        $this->signMonthly($fixture['employee'], $fixture['monthly'], 'employee');
        $this->signMonthly($fixture['supervisor'], $fixture['monthly'], 'atasan_langsung');
        $this->signMonthly($fixture['second'], $fixture['monthly'], 'atasan_kedua');
        $signatureIds = KpiSignature::where('signable_type', KpiMonthly::class)
            ->where('signable_id', $fixture['monthly']->id)->pluck('id')->all();

        $this->actingAs($fixture['superAdmin'])->post(route('dashboard.kpi.correct'), [
            'period_id' => $fixture['monthly']->participant->kpi_period_id,
            'participant_id' => $fixture['monthly']->kpi_participant_id,
            'component' => 'mpa',
            'reason' => 'Koreksi nilai MPA',
            'payload' => [
                'kinerja_operasional' => 10,
                'sikap_kerja' => 10,
                'team_work' => 10,
                'inisiatif' => 10,
                'performance' => 'Updated',
                'coaching' => 'Updated',
            ],
        ])->assertRedirect();

        $this->assertSame($signatureIds, KpiSignature::where('signable_type', KpiMonthly::class)
            ->where('signable_id', $fixture['monthly']->id)->pluck('id')->all());
        $this->assertSame(4, KpiSignature::where('signable_type', KpiMonthly::class)
            ->where('signable_id', $fixture['monthly']->id)->count());
    }

    /** @return array<string, User|KpiMonthly|null> */
    private function monthlyFixture(bool $withSecond): array
    {
        $employee = $this->actor('user', 'employee');
        $supervisor = $this->actor('admin', 'supervisor');
        $second = $withSecond ? $this->actor('admin', 'second') : null;
        $superAdmin = $this->actor('super_admin', 'super-admin');
        $period = KpiPeriod::create(['bulan' => 10, 'tahun' => 2026, 'status' => 'active']);
        $participant = KpiParticipant::create([
            'kpi_period_id' => $period->id,
            'karyawan_id' => $employee->karyawan_id,
            'jabatan_id' => $employee->karyawan->jabatan_id,
            'jabatan_snapshot' => 'Karyawan',
            'atasan_langsung_id' => $supervisor->karyawan_id,
            'atasan_langsung_snapshot' => 'Supervisor',
            'atasan_kedua_id' => $second?->karyawan_id,
            'atasan_kedua_snapshot' => $second ? 'Atasan Kedua' : null,
            'status' => 'active',
        ]);
        $monthly = KpiMonthly::create([
            'kpi_participant_id' => $participant->id,
            'status' => 'completed',
            'completed_at' => now(),
            'mpa_score' => 4,
            'attendance_score' => 4,
        ]);
        KpiSignature::create([
            'signable_type' => KpiMonthly::class,
            'signable_id' => $monthly->id,
            'role' => 'hrd_publish',
            'source' => 'automatic',
            'signed_for_user_id' => $superAdmin->id,
            'signed_by_user_id' => $superAdmin->id,
        ]);

        return compact('employee', 'supervisor', 'second', 'superAdmin', 'monthly');
    }

    private function actor(string $roleName, string $name): User
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => $roleName])->id]);
        $user->karyawan->update([
            'nama' => $name,
            'foto_tanda_tangan' => 'karyawan/'.$name.'.png',
        ]);
        Storage::disk('local')->put('karyawan/'.$name.'.png', $name);

        return $user->refresh();
    }

    private function signMonthly(User $actor, KpiMonthly $monthly, string $slot): void
    {
        $this->actingAs($actor)->post(route('dashboard.kpi.sign'), [
            'signable_type' => 'monthly',
            'signable_id' => $monthly->id,
            'signature_slot' => $slot,
        ])->assertRedirect();
    }
}
