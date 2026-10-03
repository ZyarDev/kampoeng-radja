<?php

namespace Tests\Unit;

use App\Models\KpiPeriod;
use App\Services\KpiWorkingPeriodResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class KpiWorkflowRulesTest extends TestCase
{
    public function test_closing_window_handles_december_to_january_rollover(): void
    {
        $period = new KpiPeriod(['bulan' => 12, 'tahun' => 2026, 'status' => 'active']);
        $resolver = new KpiWorkingPeriodResolver();

        $this->assertTrue($resolver->isClosingWindow($period, Carbon::parse('2027-01-08 23:59:59', 'Asia/Jakarta')));
        $this->assertFalse($resolver->isClosingWindow($period, Carbon::parse('2027-01-09 00:00:00', 'Asia/Jakarta')));
    }

    public function test_closing_window_supports_thirty_day_months(): void
    {
        $period = new KpiPeriod(['bulan' => 4, 'tahun' => 2027, 'status' => 'active']);
        $resolver = new KpiWorkingPeriodResolver();

        $this->assertTrue($resolver->isClosingWindow($period, Carbon::parse('2027-05-01 00:00:00', 'Asia/Jakarta')));
    }

    public function test_closing_window_supports_february_non_leap_and_leap_years(): void
    {
        $resolver = new KpiWorkingPeriodResolver();
        $nonLeap = new KpiPeriod(['bulan' => 2, 'tahun' => 2027, 'status' => 'active']);
        $leap = new KpiPeriod(['bulan' => 2, 'tahun' => 2028, 'status' => 'active']);

        $this->assertTrue($resolver->isClosingWindow($nonLeap, Carbon::parse('2027-03-08 23:59:59', 'Asia/Jakarta')));
        $this->assertTrue($resolver->isClosingWindow($leap, Carbon::parse('2028-03-08 23:59:59', 'Asia/Jakarta')));
    }

    public function test_previous_period_remains_default_during_closing_window(): void
    {
        $resolver = new KpiWorkingPeriodResolver();
        $previous = new KpiPeriod(['id' => 1, 'bulan' => 9, 'tahun' => 2026, 'status' => 'active']);
        $current = new KpiPeriod(['id' => 2, 'bulan' => 10, 'tahun' => 2026, 'status' => 'active']);

        $selected = $resolver->resolveDefault(
            new Collection([$current, $previous]),
            Carbon::parse('2026-10-01 12:00:00', 'Asia/Jakarta'),
        );

        $this->assertSame(1, $selected?->id);
    }

    public function test_current_period_becomes_default_after_closing_window(): void
    {
        $resolver = new KpiWorkingPeriodResolver();
        $previous = new KpiPeriod(['id' => 1, 'bulan' => 9, 'tahun' => 2026, 'status' => 'active']);
        $current = new KpiPeriod(['id' => 2, 'bulan' => 10, 'tahun' => 2026, 'status' => 'active']);

        $selected = $resolver->resolveDefault(
            new Collection([$current, $previous]),
            Carbon::parse('2026-10-09 00:00:00', 'Asia/Jakarta'),
        );

        $this->assertSame(2, $selected?->id);
    }

    public function test_signature_slot_is_canonical_with_role_as_compatibility_fallback(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Kpi/KpiController.php'));

        $this->assertStringContainsString("\$v['signature_slot'] ?? \$v['role']", $source);
        $this->assertStringContainsString("'signature_slot' => 'nullable|string", $source);
    }

    public function test_late_signature_flow_has_no_actorless_signature_creation(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Kpi/KpiController.php'));

        $this->assertStringNotContainsString("'signed_by_user_id' => null", $source);
        $this->assertStringContainsString("'super_admin_takeover'", $source);
    }

    public function test_kpi_signature_clients_send_signature_slot(): void
    {
        foreach (['Individual.vue', 'Ops.vue', 'FinalScore.vue', 'Monthly.vue'] as $page) {
            $source = file_get_contents(resource_path('js/Pages/Internal/Kpi/'.$page));
            $this->assertStringContainsString('signature_slot', $source, $page.' must use the canonical signature slot payload.');
        }
    }

    public function test_monthly_completion_requires_all_four_logical_slots(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Kpi/KpiController.php'));

        $this->assertStringContainsString("['hrd_publish', 'employee', 'atasan_langsung', 'atasan_kedua']", $source);
    }

    public function test_super_admin_assistance_and_takeover_are_distinct_sources(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Kpi/KpiController.php'));

        $this->assertStringContainsString("'super_admin_assistance'", $source);
        $this->assertStringContainsString("'super_admin_takeover'", $source);
    }

    public function test_mpa_adjustment_is_gated_by_monthly_signatures(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Kpi/KpiController.php'));

        $this->assertStringContainsString('$rpScore = $monthlySignaturesComplete', $source);
    }
}
