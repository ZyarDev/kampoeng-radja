<?php

namespace Tests\Feature\Kpi;

use App\Models\KpiIndividualScore;
use App\Models\KpiOpsItem;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\KpiSignature;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiDeadlineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ki_and_ops_remain_draft_at_deadline_and_become_not_filled_after_it(): void
    {
        $fixture = $this->fixture();

        Carbon::setTestNow(Carbon::parse('2026-10-01 23:59:59', 'Asia/Jakarta'));
        $this->artisan('kpi:process-deadlines')->assertExitCode(0);
        $this->assertSame('draft', $fixture['ki']->refresh()->status);
        $this->assertSame('draft', $fixture['ops']->refresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-10-02 00:00:01', 'Asia/Jakarta'));
        $this->artisan('kpi:process-deadlines')->assertExitCode(0);

        $this->assertDatabaseHas('kpi_individual_scores', [
            'id' => $fixture['ki']->id,
            'status' => 'not_filled',
            'submit_type' => 'automatic',
            'score' => 0,
        ]);
        $this->assertDatabaseHas('kpi_ops_items', [
            'id' => $fixture['ops']->id,
            'status' => 'not_filled',
            'submit_type' => 'automatic',
            'nilai_item' => 0,
        ]);
        $this->assertSame(0, KpiSignature::count());
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['nama_role' => 'user'])->id]);
        $period = KpiPeriod::create(['bulan' => 9, 'tahun' => 2026, 'status' => 'active']);
        $participant = KpiParticipant::create([
            'kpi_period_id' => $period->id,
            'karyawan_id' => $user->karyawan_id,
            'jabatan_id' => $user->karyawan->jabatan_id,
            'jabatan_snapshot' => 'Pengujian',
            'status' => 'active',
        ]);
        $ki = KpiIndividualScore::create(['kpi_participant_id' => $participant->id, 'status' => 'draft']);
        $ops = KpiOpsItem::create([
            'kpi_participant_id' => $participant->id,
            'urutan' => 1,
            'kpi_item' => 'Item uji',
            'target_unit' => 1,
            'target_bulanan' => 1,
            'beban_target' => 1,
            'sumber_data_snapshot' => 'Uji',
            'status' => 'draft',
        ]);
        return compact('ki', 'ops');
    }
}
