<?php

namespace App\Exports\Kpi;

use App\Models\Absensi;
use App\Models\KpiDailyReport;
use App\Models\KpiFinalScore;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\KpiSignature;
use App\Support\KpiClock;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class EmployeeKpiExport implements WithMultipleSheets
{
    private KpiParticipant $participant;
    private Collection $reports;
    private Collection $attendance;
    private ?KpiFinalScore $final;
    private Collection $finalSignatures;

    public function __construct(private readonly KpiPeriod $period, int $employeeId)
    {
        $this->participant = KpiParticipant::query()
            ->with([
                'karyawan.jabatan', 'karyawan.departemen', 'karyawan.penempatan',
                'individualScore.signatures.signedBy.karyawan', 'opsItems',
                'monthly.attendanceAdjustments', 'monthly.rewardPunishments', 'monthly.signatures.signedBy.karyawan',
                'signatures.signedBy.karyawan',
                'atasanLangsung', 'atasanKedua',
            ])
            ->where('kpi_period_id', $period->id)
            ->where('karyawan_id', $employeeId)
            ->firstOrFail();
        $this->reports = KpiDailyReport::query()
            ->with(['activities', 'approver.karyawan', 'atasanSnapshot'])
            ->where('karyawan_id', $employeeId)
            ->whereBetween('tanggal', [$this->periodStart()->toDateString(), $this->periodEnd()->toDateString()])
            ->orderBy('tanggal')->get()->keyBy(fn (KpiDailyReport $report) => $report->tanggal->toDateString());
        $this->attendance = Absensi::query()
            ->where('karyawan_id', $employeeId)
            ->whereBetween('tanggal_absensi', [$this->periodStart()->toDateString(), $this->periodEnd()->toDateString()])
            ->get()->keyBy(fn (Absensi $record) => $record->tanggal_absensi->toDateString());
        $this->final = KpiFinalScore::query()->where('kpi_participant_id', $this->participant->id)->first();
        $this->finalSignatures = $this->final
            ? KpiSignature::query()->with('signedBy.karyawan')->where('signable_type', KpiFinalScore::class)->where('signable_id', $this->final->id)->get()
            : collect();
    }

    public function sheets(): array
    {
        $sheets = [
            new KpiSheet('Nilai Akhir', $this->finalRows(), $this->finalImages()),
            new KpiSheet('Monthly', $this->monthlyRows(), $this->monthlyImages()),
            new KpiSheet('Kinerja Individu', $this->individualRows(), $this->individualImages()),
            new KpiSheet('Kinerja OPS', $this->opsRows(), $this->opsImages(), PageSetup::ORIENTATION_LANDSCAPE),
        ];
        $date = $this->periodStart();
        while ($date->lessThanOrEqualTo($this->periodEnd())) {
            $sheets[] = new KpiSheet('DR '.$date->format('d'), $this->dailyRows($date), $this->dailyImages($date));
            $date->addDay();
        }
        return $sheets;
    }

    private function identityRows(): array
    {
        $p = $this->participant;
        return [
            ['Nama', $p->karyawan?->nama ?? '-'], ['NIK', $p->karyawan?->nik ?? '-'],
            ['Jabatan', $p->jabatan_snapshot ?: '-'], ['Departemen', $p->departemen_snapshot ?: '-'],
            ['Penempatan', $p->penempatan_snapshot ?: '-'], ['Periode', $this->periodLabel()],
        ];
    }

    private function finalRows(): array
    {
        $rows = [['NILAI AKHIR KPI'], ...$this->identityRows(), [''], ['Komponen', 'Nilai'],
            ['Kinerja Individu', $this->final?->ki_score ?? '-'], ['Kinerja OPS', $this->final?->ops_score ?? '-'],
            ['MPA', $this->final?->mpa_score ?? '-'], ['Absensi', $this->final?->attendance_score ?? '-'],
            ['Reward/Punishment', $this->final?->reward_punishment_score ?? '-'],
            ['Nilai Akhir', $this->final?->score ?? '-'], ['Kategori', $this->final?->kategori ?? '—'], ['Status', $this->final?->status ?? 'Nilai Akhir belum tersedia'], [''], ['TANDA TANGAN NILAI AKHIR']];
        return [...$rows, ...$this->signatureRows($this->finalSignatures, ['employee', 'atasan_langsung'])];
    }

    private function monthlyRows(): array
    {
        $m = $this->participant->monthly;
        $rows = [['MONTHLY PERFORMANCE APPRAISAL'], ...$this->identityRows(), [''], ['Kinerja Operasional', $m?->kinerja_operasional ?? '-'], ['Sikap Kerja', $m?->sikap_kerja ?? '-'], ['Team Work', $m?->team_work ?? '-'], ['Inisiatif', $m?->inisiatif ?? '-'], ['Kepemimpinan', $m?->kepemimpinan ?? 'N/A'], ['Performance', $m?->performance ?? '-'], ['Coaching', $m?->coaching ?? '-'], ['Nilai MPA', $m?->mpa_score ?? '-'], ['Nilai Absensi', $m?->attendance_score ?? '-'], ['Reward/Punishment', $m?->reward_punishment_score ?? '-'], ['Status', $m?->status ?? 'Monthly belum tersedia']];
        if ($m?->attendanceAdjustments?->isNotEmpty()) {
            $rows[] = ['Penilaian Absensi'];
            foreach ($m->attendanceAdjustments as $adjustment) $rows[] = [$adjustment->kode, $adjustment->jumlah, $adjustment->nilai];
        }
        if ($m?->rewardPunishments?->isNotEmpty()) {
            $rows[] = ['Reward/Punishment Detail'];
            foreach ($m->rewardPunishments as $reward) $rows[] = [$reward->jenis, $reward->jumlah, $reward->nilai];
        }
        $rows = [...$rows, [''], ['PERSETUJUAN']];
        if ($m) {
            foreach ($m->signatures as $signature) $rows = [...$rows, ...$this->signatureRows(collect([$signature]), [])];
        }
        return $rows;
    }

    private function individualRows(): array
    {
        $s = $this->participant->individualScore;
        $rows = [['KINERJA INDIVIDU'], ...$this->identityRows(), [''], ['Capaian Departemen', $s?->capaian_departemen ?? '-'], ['Perawatan Aset', $s?->perawatan_aset ?? '-'], ['Kebersihan/Kerapihan', $s?->kebersihan_kerapihan ?? '-'], ['Nilai', $s?->score ?? '-'], ['Status', $s?->status ?? 'Kinerja Individu belum selesai'], [''], ['PERSETUJUAN']];
        return [...$rows, ...$this->signatureRows($s?->signatures ?? collect(), [])];
    }

    private function opsRows(): array
    {
        $rows = [['KINERJA OPERASIONAL'], ...$this->identityRows(), [''], ['KPI Item', 'Maintenance', 'Target Unit', 'Tanda', 'Frekuensi', 'Beban Target', 'Hasil', 'Nilai', 'Aktivitas']];
        foreach ($this->participant->opsItems as $item) $rows[] = [$item->kpi_item, $item->maintenance ?: '-', $item->target_unit, $item->tanda ?: '-', $item->frekuensi ?: '-', $item->beban_target, $item->hasil ?? '-', $item->nilai_item, $item->aktivitas ?: '-'];
        if ($this->participant->opsItems->isEmpty()) $rows[] = ['Parameter Kinerja OPS belum ditetapkan.'];
        $rows[] = ['']; $rows[] = ['PERSETUJUAN'];
        return [...$rows, ...$this->signatureRows($this->participant->signatures, [])];
    }

    private function dailyRows(Carbon $date): array
    {
        $dateKey = $date->toDateString(); $attendance = $this->attendance->get($dateKey); $report = $this->reports->get($dateKey);
        $rows = [['DAILY REPORT'], [$date->locale('id')->translatedFormat('d F Y')], ...$this->identityRows(), ['Atasan Langsung', $this->participant->atasan_langsung_snapshot ?: '-'], ['Tanggal', $dateKey], ['']];
        if ($date->gt(KpiClock::today())) return [...$rows, ['Tanggal belum terjadi.'], ['Daily Report belum tersedia.']];
        if (! $attendance || $attendance->status_kehadiran !== 'H') return [...$rows, ['Status Kehadiran', $attendance?->status_kehadiran ?: 'Tidak dijadwalkan'], ['Daily Report tidak diwajibkan pada tanggal ini.']];
        if (! $report) return [...$rows, ['Status Daily', 'TIDAK DIISI']];
        $rows[] = ['Status Daily', $report->status]; $rows[] = ['']; $rows[] = ['No', 'Aktivitas / Kontribusi', 'Hasil', 'Keterangan'];
        foreach ($report->activities as $index => $activity) $rows[] = [$index + 1, $activity->rincian_kegiatan, '-', $activity->keterangan ?: '-'];
        return [...$rows, [''], ['PERSETUJUAN'], ...$this->dailySignatureRows($report)];
    }

    private function signatureRows(Collection $signatures, array $preferredRoles): array
    {
        $ordered = $preferredRoles ? $signatures->sortBy(fn ($s) => array_search($s->role, $preferredRoles, true)) : $signatures;
        $rows = [];
        foreach ($ordered as $signature) {
            $name = $signature->signedBy?->karyawan?->nama ?? $signature->signedBy?->name ?? '-';
            $status = $signature->source === 'super_admin_takeover' ? 'DIALIHKAN SUPER ADMIN' : ($signature->role === 'employee' ? 'Ditandatangani Karyawan' : 'Disetujui Atasan');
            $rows = [...$rows, ['Peran', $signature->role], ['Nama actual signer', $name], ['Signed at', $signature->signed_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' ?: '-'], ['Status', $status], ['Signature', $signature->signature_path ? 'Tersedia' : 'Tanda tangan digital tidak tersedia'], ['']];
        }
        return $rows ?: [['Belum ada signature.']];
    }

    private function dailySignatureRows(KpiDailyReport $report): array
    {
        $name = $report->approver?->karyawan?->nama ?? $report->atasanSnapshot?->nama ?? '-';
        $status = $report->approval_source === 'super_admin_takeover' ? 'DIALIHKAN' : 'Disetujui Atasan';
        return [['Nama actual signer', $name], ['Approved at', $report->approved_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' ?: '-'], ['Sumber Persetujuan', $report->approval_source ?: '-'], ['Status', $status], ['Signature', $report->approval_signature_path ? 'Tersedia' : 'Tanda tangan digital tidak tersedia']];
    }

    private function signatureImages(Collection $signatures, array $rows): array
    {
        $signatureRows = collect($rows)->map(fn (array $row, int $index) => ($row[0] ?? null) === 'Signature' ? $index + 1 : null)->filter()->values();

        return $signatures
            ->filter(fn ($signature) => $this->storagePath($signature->signature_path))
            ->values()
            ->map(fn ($signature, int $index) => [
                'name' => 'Signature',
                'path' => $this->storagePath($signature->signature_path),
                'coordinate' => 'D'.($signatureRows->get($index) ?? 1),
            ])->all();
    }

    private function finalImages(): array { return $this->signatureImages($this->finalSignatures, $this->finalRows()); }
    private function monthlyImages(): array { return $this->signatureImages($this->participant->monthly?->signatures ?? collect(), $this->monthlyRows()); }
    private function individualImages(): array { return $this->signatureImages($this->participant->individualScore?->signatures ?? collect(), $this->individualRows()); }
    private function opsImages(): array { return $this->signatureImages($this->participant->signatures, $this->opsRows()); }
    private function dailyImages(Carbon $date): array
    {
        $report = $this->reports->get($date->toDateString());
        $path = $report ? $this->storagePath($report->approval_signature_path) : null;
        if (! $report || ! $path) return [];
        $rows = $this->dailyRows($date);
        $signatureRow = collect($rows)->search(fn (array $row) => ($row[0] ?? null) === 'Signature');
        return [['name' => 'Daily signature', 'path' => $path, 'coordinate' => 'D'.(($signatureRow === false ? 0 : $signatureRow) + 1)]];
    }

    private function storagePath(?string $path): ?string
    {
        if (! $path) return null;
        $path = preg_replace('#^/?storage/#', '', trim($path));
        return Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null;
    }

    private function periodStart(): Carbon { return Carbon::create($this->period->tahun, $this->period->bulan, 1, 0, 0, 0, 'Asia/Jakarta'); }
    private function periodEnd(): Carbon { return $this->periodStart()->endOfMonth(); }
    private function periodLabel(): string { return $this->periodStart()->locale('id')->translatedFormat('F Y'); }
}
