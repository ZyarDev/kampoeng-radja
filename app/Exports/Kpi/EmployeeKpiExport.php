<?php

namespace App\Exports\Kpi;

use App\Models\Absensi;
use App\Models\KpiDailyReport;
use App\Models\KpiFinalScore;
use App\Models\KpiMonthly;
use App\Models\KpiParticipant;
use App\Models\KpiPeriod;
use App\Models\KpiSignature;
use App\Support\KpiClock;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/** Export KPI data by loading the approved workbook template as the visual source of truth. */
class EmployeeKpiExport
{
    private Spreadsheet $workbook;
    private KpiParticipant $participant;
    private Collection $reports;
    private Collection $attendance;
    private ?KpiFinalScore $final;
    private Collection $finalSignatures;
    private array $temporaryImages = [];

    public function __construct(private readonly KpiPeriod $period, int $employeeId)
    {
        $this->participant = KpiParticipant::query()->with([
            'karyawan.jabatan', 'karyawan.departemen', 'karyawan.penempatan',
            'individualScore.signatures.signedBy.karyawan', 'opsItems', 'signatures.signedBy.karyawan',
            'monthly.evaluator.karyawan', 'monthly.attendanceAdjustments', 'monthly.rewardPunishments', 'monthly.signatures.signedBy.karyawan', 'monthly.signatures.signedBy.role',
            'atasanLangsung', 'atasanKedua',
        ])->where('kpi_period_id', $period->id)->where('karyawan_id', $employeeId)->firstOrFail();
        $this->reports = KpiDailyReport::query()->with(['activities', 'approver.karyawan', 'atasanSnapshot'])
            ->where('karyawan_id', $employeeId)->whereBetween('tanggal', [$this->periodStart()->toDateString(), $this->periodEnd()->toDateString()])
            ->orderBy('tanggal')->get()->keyBy(fn (KpiDailyReport $report) => $report->tanggal->toDateString());
        $this->attendance = Absensi::query()->where('karyawan_id', $employeeId)->whereBetween('tanggal_absensi', [$this->periodStart()->toDateString(), $this->periodEnd()->toDateString()])
            ->get()->keyBy(fn (Absensi $record) => $record->tanggal_absensi->toDateString());
        $this->final = KpiFinalScore::where('kpi_participant_id', $this->participant->id)->first();
        $this->finalSignatures = $this->final ? KpiSignature::with('signedBy.karyawan')->where('signable_type', KpiFinalScore::class)->where('signable_id', $this->final->id)->get() : collect();
    }

    public function save(): string
    {
        $template = resource_path('templates/kpi/employee-kpi-template.xlsx');
        abort_unless(is_file($template), 500, 'Template export KPI tidak ditemukan.');
        $this->workbook = IOFactory::load($template);
        $this->populateFinalScore(); $this->populateMonthly(); $this->populateIndividual(); $this->populateOps(); $this->populateDailySheets();
        $path = tempnam(storage_path('app'), 'kpi_export_');
        try { $writer = IOFactory::createWriter($this->workbook, 'Xlsx'); $writer->setPreCalculateFormulas(false); $writer->save($path); return $path; }
        finally { foreach ($this->temporaryImages as $image) @unlink($image); }
    }

    private function populateFinalScore(): void
    {
        $sheet = $this->workbook->getSheetByName('Nilai Akhir'); $this->set($sheet, 'A2', 'Periode '.$this->periodLabel()); $this->identity($sheet, ['B5', 'B6', 'B7'], ['E5', 'E6', 'E7']);
        $final = $this->final; foreach (['E11' => $final?->ki_score, 'E12' => $final?->ops_score, 'E13' => $final?->mpa_score, 'E14' => $this->finalAttendanceScore(), 'E15' => $final?->reward_punishment_score, 'E16' => $final?->score, 'E17' => $final?->kategori, 'E18' => $final?->reward_punishment_score] as $cell => $value) $this->set($sheet, $cell, $value ?? '-');
        $signature = $this->preferredSignature($this->finalSignatures, ['employee', 'atasan_langsung']); $employeeSignature = $this->finalSignatures->firstWhere('role', 'employee');
        $finalStatus = $employeeSignature ? '●  Selesai' : ($final?->status === 'completed' ? '●  Menunggu TTD Karyawan' : '●  '.($final?->status ?: 'Belum tersedia'));
        $this->set($sheet, 'C20', $finalStatus);
        $this->signatureSlot($sheet, 'A23', 'A28', 'A29', 'A30', $signature, $signature ? $this->signatureStatus($signature) : null);
    }

    private function populateMonthly(): void
    {
        $sheet = $this->workbook->getSheetByName('Monthly'); $monthly = $this->participant->monthly; $this->set($sheet, 'A3', $this->periodLabel()); $this->identity($sheet, ['B6', 'B7', 'B8'], ['E6', 'E7', 'E8']);
        $this->set($sheet, 'J6', $monthly?->evaluator?->karyawan?->nama ?? '-'); $this->set($sheet, 'J7', $this->monthlyStatusLabel($monthly?->status));
        $criteria = $this->monthlyCriteria(); $values = ['kinerja_operasional', 'sikap_kerja', 'team_work', 'inisiatif', 'kepemimpinan'];
        foreach ($criteria as $index => $criterion) { $row = 13 + $index; $this->set($sheet, "A{$row}", $criterion['label']); $this->set($sheet, "C{$row}", $criterion['low']); $this->set($sheet, "F{$row}", $criterion['standard']); $this->set($sheet, "I{$row}", $criterion['high']); $this->set($sheet, "L{$row}", $monthly?->{$values[$index]} ?? ($index === 4 ? 'N/A' : '-')); $sheet->getRowDimension($row)->setRowHeight(98); }
        $this->set($sheet, 'L19', $monthly?->mpa_score ?? '-'); $this->fillHrdRows($sheet, $monthly); $this->set($sheet, 'A32', $monthly?->performance ?? ''); $this->set($sheet, 'A37', $monthly?->coaching ?? '');
        foreach (['G42' => $monthly?->mpa_score, 'G43' => $this->monthlyAttendanceScore($monthly), 'G44' => $monthly?->reward_punishment_score, 'G45' => $monthly?->status] as $cell => $value) $this->set($sheet, $cell, $value ?? '-');
        $signatures = $monthly?->signatures ?? collect(); $this->signatureSlot($sheet, 'A49', 'A53', 'A54', 'A55', $this->signatureFor($signatures, 'hrd_publish'), 'Finalisasi HRD', 'Finalisasi HRD');
        $second = $this->signatureFor($signatures, 'atasan_kedua'); $secondReplaced = ! $this->participant->atasan_kedua_id; if ($secondReplaced && ! $second) $second = $signatures->first(fn ($s) => $s->source === 'super_admin_takeover' && $s->role !== 'employee') ?: $signatures->first(fn ($s) => $s->signedBy?->role?->nama_role === 'super_admin' && $s->role !== 'employee');
        $this->signatureSlot($sheet, 'D49', 'D53', 'D54', 'D55', $second, $second ? $this->signatureStatus($second) : 'Belum ditandatangani', $secondReplaced && $second ? 'Dialihkan' : null); $this->signatureSlot($sheet, 'H49', 'H53', 'H54', 'H55', $this->signatureFor($signatures, 'atasan_langsung'), 'Disetujui Atasan'); $this->signatureSlot($sheet, 'L49', 'L53', 'L54', 'L55', $this->signatureFor($signatures, 'employee'), 'Ditandatangani Karyawan');
    }

    private function populateIndividual(): void
    {
        $sheet = $this->workbook->getSheetByName('Kinerja Individu'); $score = $this->participant->individualScore; $this->set($sheet, 'A2', $this->periodLabel()); $this->identity($sheet, ['B5', 'B6', 'B7'], ['E5', 'E6', 'E7']);
        $components = [['row' => 11, 'value' => $score?->capaian_departemen, 'weight' => 0.70], ['row' => 12, 'value' => $score?->perawatan_aset, 'weight' => 0.05], ['row' => 13, 'value' => $score?->kebersihan_kerapihan, 'weight' => 0.05]];
        foreach ($components as $component) { $row = $component['row']; $this->set($sheet, "C{$row}", $component['weight']); $this->set($sheet, "D{$row}", $component['value'] ?? '-'); $this->set($sheet, "E{$row}", $component['value'] === null ? '-' : round($component['weight'] * $component['value'], 2)); }
        $this->set($sheet, 'C14', array_sum(array_column($components, 'weight'))); $this->set($sheet, 'E14', $score?->score ?? '-'); $this->set($sheet, 'C16', $score?->status === 'approved' ? '●  Selesai' : '●  '.($score?->status ?? 'Belum tersedia'));
        $signature = $this->preferredSignature($score?->signatures ?? collect(), ['atasan_langsung']); $this->signatureSlot($sheet, 'A20', 'A24', 'A25', 'A26', $signature, $signature ? $this->signatureStatus($signature) : null);
    }

    private function populateOps(): void
    {
        $sheet = $this->workbook->getSheetByName('Kinerja OPS'); $items = $this->participant->opsItems; $this->set($sheet, 'A2', $this->periodLabel()); $this->identity($sheet, ['B5', 'B6', 'B7'], ['H5', 'H6', 'H7']); $this->set($sheet, 'I10', mb_strtoupper($this->monthName()));
        $delta = $items->count() - 5; if ($delta < 0) $sheet->removeRow(13, abs($delta)); if ($delta > 0) { $sheet->insertNewRowBefore(13, $delta); for ($row = 13; $row < 13 + $delta; $row++) $sheet->duplicateStyle($sheet->getStyle('A12:K12'), "A{$row}:K{$row}"); }
        foreach ($items as $index => $item) { $row = 12 + $index; foreach (["A{$row}" => $index + 1, "B{$row}" => $item->kpi_item ?: '-', "C{$row}" => $item->maintenance ?: '-', "D{$row}" => $item->target_unit, "E{$row}" => $item->tanda ?: '-', "F{$row}" => $this->percentValue($item->beban_target), "G{$row}" => $item->sumber_data_snapshot ?: '-', "H{$row}" => $item->frekuensi ?: '-', "I{$row}" => $item->target_bulanan ?? $item->target_unit, "J{$row}" => $item->hasil ?? '-', "K{$row}" => $item->aktivitas ?: '-'] as $cell => $value) $this->set($sheet, $cell, $value); }
        if ($items->isEmpty()) $this->set($sheet, 'B12', 'Parameter Kinerja OPS belum ditetapkan.'); $totalRow = 17 + $delta; $statusRow = 19 + $delta; $signRow = 21 + $delta;
        foreach (["F{$totalRow}" => $this->percentValue($items->sum(fn ($i) => (float) ($i->beban_target))), "H{$totalRow}" => '-', "I{$totalRow}" => $items->sum(fn ($i) => (float) ($i->target_bulanan ?? $i->target_unit)), "J{$totalRow}" => $items->sum(fn ($i) => (float) ($i->hasil ?? 0)), "G{$statusRow}" => $items->isNotEmpty() && $items->every(fn ($i) => in_array($i->status, ['approved', 'locked', 'auto_signed', 'not_filled'], true)) ? '●  Selesai' : '●  Belum selesai'] as $cell => $value) $this->set($sheet, $cell, $value);
        $signatures = $this->participant->signatures; $this->signatureSlot($sheet, 'A'.($signRow + 2), 'A'.($signRow + 6), 'A'.($signRow + 7), 'A'.($signRow + 8), $this->signatureFor($signatures, 'atasan_langsung'), 'Disetujui Atasan'); $this->signatureSlot($sheet, 'G'.($signRow + 2), 'G'.($signRow + 6), 'G'.($signRow + 7), 'G'.($signRow + 8), $this->signatureFor($signatures, 'employee'), 'Ditandatangani Karyawan');
    }

    private function populateDailySheets(): void
    {
        $master = $this->workbook->getSheetByName('DR 01'); $sheets = [$master]; for ($day = 2; $day <= $this->periodEnd()->day; $day++) { $clone = clone $master; $clone->setTitle(sprintf('DR %02d', $day)); $this->workbook->addSheet($clone); $sheets[] = $clone; }
        foreach ($sheets as $index => $sheet) $this->populateDaily($sheet, $this->periodStart()->day($index + 1)); $this->workbook->setActiveSheetIndex(0);
    }

    private function populateDaily($sheet, Carbon $date): void
    {
        $report = $this->reports->get($date->toDateString()); $attendance = $this->attendance->get($date->toDateString()); $this->set($sheet, 'A2', $date->locale('id')->translatedFormat('d F Y')); $this->identity($sheet, ['B5', 'B6', 'B7'], ['F5', 'F6', 'F7']);
        if ($report && $attendance?->status_kehadiran === 'H' && $report->activities->isNotEmpty()) {
            $count = $report->activities->count(); $delta = $count - 6; if ($delta < 0) $sheet->removeRow(12, abs($delta)); if ($delta > 0) { $sheet->insertNewRowBefore(17, $delta); for ($row = 17; $row < 17 + $delta; $row++) $sheet->duplicateStyle($sheet->getStyle('A16:G16'), "A{$row}:G{$row}"); }
            foreach ($report->activities as $index => $activity) { $row = 11 + $index; $this->set($sheet, "A{$row}", $index + 1); $this->set($sheet, "B{$row}", $activity->rincian_kegiatan); $this->set($sheet, "F{$row}", $activity->keterangan ?: '-'); }
            $statusRow = 12 + $count; $statusRow += $this->renderEvidence($sheet, $statusRow, $report); $this->set($sheet, "E{$statusRow}", $this->dailyStatus($report)); $signRow = $statusRow + 2; $this->dailySignatureSlot($sheet, $signRow, $report, $report->approval_signature_path ? $report : null); return;
        }
        $sheet->removeRow(10, 7); $this->set($sheet, 'A9', 'STATUS DAILY REPORT');
        if ($date->gt(KpiClock::today())) { $this->set($sheet, 'A10', 'Tanggal belum terjadi. Daily Report belum tersedia.'); $this->set($sheet, 'A11', 'Belum tersedia'); }
        elseif ($attendance?->status_kehadiran !== 'H') { $this->set($sheet, 'A10', 'Status Kehadiran: '.($attendance?->status_kehadiran ?: 'Tidak dijadwalkan').'. Daily Report tidak diwajibkan pada tanggal ini.'); $this->set($sheet, 'A11', 'Tidak diwajibkan'); }
        else { $this->set($sheet, 'A10', 'Daily Report wajib diisi pada tanggal ini, tetapi belum tersedia.'); $this->set($sheet, 'A11', 'TIDAK DIISI'); }
        $sheet->getStyle('A10:G10')->getAlignment()->setWrapText(true); $sheet->getRowDimension(10)->setRowHeight(38); $this->clearDailySignature($sheet, 13);
    }

    private function renderEvidence($sheet, int $row, KpiDailyReport $report): int
    {
        $photos = $report->activities->map(fn ($a) => ['path' => $this->storagePath($a->bukti_path), 'caption' => 'Aktivitas #'.$a->urutan.' — '.mb_substr((string) $a->rincian_kegiatan, 0, 55)])->filter(fn ($p) => $p['path'])->values(); if ($photos->isEmpty()) return 0;
        $photoRows = (int) ceil($photos->count() / 3); $blockRows = 1 + ($photoRows * 7); $sheet->insertNewRowBefore($row, $blockRows); $source = $row + $blockRows; for ($i = 0; $i < $blockRows; $i++) $sheet->duplicateStyle($sheet->getStyle("A{$source}:G{$source}"), 'A'.($row + $i).':G'.($row + $i)); $sheet->mergeCells("A{$row}:G{$row}"); $this->set($sheet, "A{$row}", 'BUKTI KEGIATAN');
        foreach ($photos->chunk(3) as $groupIndex => $group) { $imageRow = $row + 1 + ($groupIndex * 7); foreach ($group->values() as $photoIndex => $photo) { [$from, $to] = [['A', 'B'], ['C', 'E'], ['F', 'G']][$photoIndex]; $sheet->mergeCells("{$from}{$imageRow}:{$to}".($imageRow + 4)); $sheet->mergeCells("{$from}".($imageRow + 5).":{$to}".($imageRow + 6)); if ($image = $this->thumbnail($photo['path'])) { $drawing = new Drawing(); $drawing->setName('Bukti kegiatan'); $drawing->setDescription($photo['caption']); $drawing->setPath($image); $drawing->setHeight(120); $drawing->setCoordinates($from.$imageRow); $drawing->setOffsetX(4); $drawing->setOffsetY(3); $drawing->setWorksheet($sheet); } $this->set($sheet, "{$from}".($imageRow + 5), $photo['caption']); } }
        return $blockRows;
    }

    private function fillHrdRows($sheet, ?KpiMonthly $monthly): void
    {
        $attendanceRows = [['P1', .5, 'Urusan Pribadi'], ['DL', .25, 'Datang Lambat'], ['PC', .25, 'Pulang Cepat'], ['LC', .25, 'Lupa Catat'], ['M', 3, 'Mangkir']]; $actual = $monthly?->attendanceAdjustments?->keyBy('kode') ?? collect();
        foreach ($attendanceRows as $index => [$code, $defaultRate, $label]) { $row = 24 + $index; $item = $actual->get($code); $rate = $item && (int) $item->jumlah > 0 ? (float) $item->nilai / (int) $item->jumlah : $defaultRate; foreach (["A{$row}" => $label, "B{$row}" => $code, "C{$row}" => $rate, "D{$row}" => $item?->jumlah ?? 0, "E{$row}" => $item?->nilai ?? 0] as $cell => $value) $this->set($sheet, $cell, $value); }
        $rewards = [['major_award', 7, 'Jasa Besar (Major Award)'], ['minor_award', 3, 'Jasa Kecil (Minor Award)'], ['minor_demerit', -4, 'Kesalahan Ringan (Minor Demerit)'], ['major_demerit', -8, 'Kesalahan Besar (Major Demerit)']]; $actualRewards = $monthly?->rewardPunishments?->keyBy('jenis') ?? collect();
        foreach ($rewards as $index => [$type, $rate, $label]) { $row = 24 + $index; $item = $actualRewards->get($type); foreach (["H{$row}" => $label, "I{$row}" => $rate, "J{$row}" => $item?->jumlah ?? 0, "K{$row}" => $item?->nilai ?? 0] as $cell => $value) $this->set($sheet, $cell, $value); }
        $this->set($sheet, 'E29', $this->monthlyAttendanceScore($monthly)); $this->set($sheet, 'K29', $monthly?->reward_punishment_score ?? '-');
    }

    private function monthlyAttendanceScore(?KpiMonthly $monthly): float|string
    {
        if (! $monthly) return '-';
        if (! $monthly->completed_at) return '-';
        $score = $monthly->attendance_score;
        if ($monthly->completed_at && $monthly->attendanceAdjustments->isEmpty() && (float) $score === 0.0) return 5.0;
        return $score ?? '-';
    }

    private function finalAttendanceScore(): float|string
    {
        if ($this->final && $this->final->attendance_score !== null && (float) $this->final->attendance_score !== 0.0) return $this->final->attendance_score;
        return $this->monthlyAttendanceScore($this->participant->monthly);
    }

    private function trimSignatureImage(string $path): ?string
    {
        if (! function_exists('imagecreatefrompng')) return $path;
        $size = @getimagesize($path); if (! $size) return $path;
        $create = match ($size['mime'] ?? '') { 'image/png' => 'imagecreatefrompng', 'image/jpeg' => 'imagecreatefromjpeg', 'image/webp' => 'imagecreatefromwebp', default => null };
        if (! $create || ! function_exists($create)) return $path;
        $source = @$create($path); if (! $source) return $path;
        $width = imagesx($source); $height = imagesy($source); $minX = $width; $minY = $height; $maxX = -1; $maxY = -1;
        for ($y = 0; $y < $height; $y++) for ($x = 0; $x < $width; $x++) {
            $alpha = (imagecolorat($source, $x, $y) >> 24) & 0x7F;
            if ($alpha < 110) { $minX = min($minX, $x); $minY = min($minY, $y); $maxX = max($maxX, $x); $maxY = max($maxY, $y); }
        }
        if ($maxX < 0) { imagedestroy($source); return $path; }
        $padding = 8; $cropX = max(0, $minX - $padding); $cropY = max(0, $minY - $padding); $cropW = min($width - $cropX, $maxX - $minX + ($padding * 2) + 1); $cropH = min($height - $cropY, $maxY - $minY + ($padding * 2) + 1);
        $canvas = imagecreatetruecolor($cropW, $cropH); imagealphablending($canvas, false); imagesavealpha($canvas, true); $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127); imagefilledrectangle($canvas, 0, 0, $cropW, $cropH, $transparent); imagecopy($canvas, $source, 0, 0, $cropX, $cropY, $cropW, $cropH);
        $target = tempnam(sys_get_temp_dir(), 'kpi_signature_').'.png'; imagepng($canvas, $target); imagedestroy($canvas); imagedestroy($source); $this->temporaryImages[] = $target; return $target;
    }

    private function monthlyCriteria(): array
    {
        return [
            ['label' => 'Kinerja Operasional', 'low' => 'Kinerjanya dibawah rata- rata yang diharapkan atau standard. Pekerjaan harus diulangi. Mempunyai kesulitan untuk secara konsisten mempertahankan kinerja yang bisa diterima.', 'standard' => 'Secara konsisten mencapai target walaupun bekerja dibawah tekanan. Mempertahankan standard produktivitas. Bekerja dengan Tepat dan Akurat. Mampu meningkatkan kinerja walaupun dalam kondisi stress tanpa mengorbankan kualitas.', 'high' => 'Kinerjanya Luar biasa. Produktivitas dan kualitas kerjanya diatas target yang ditetapkan.'],
            ['label' => 'Sikap Kerja', 'low' => 'Kurang bersemangat atau ragu-ragu untuk menyelesaikan pekerjaan atau/dan memberikan bantuan/ pelayanan. Mencari - cari alasan karena target tidak tercapai atau mudah menyerah apabila menghadapi hambatan.', 'standard' => 'Bersemangat untuk menyelesaikan pekerjaan dan/atau memberikan bantuan/pelayanan. Mencari solusi untuk mengatasi masalah.', 'high' => 'Menunjukkan antusiasme dan usaha lebih untuk menyelesaikan pekerjaan dan /atau memberikan bantuan/pelayanan. Secara pro aktif mencari solusi untuk mengatasi masalah.'],
            ['label' => 'Team Work', 'low' => 'Kurang membina relasi yang sopan dan produktif dengan rekan kerja. Lebih mementingkan diri sendiri dari pada Team. Menempatkan kepentingan pribadi diatas kepentingan kelompok sehingga mempengaruhi pencapaian tujuan organisasi.', 'standard' => 'Membina relasi yang sopan dan saling menghargai dengan rekan kerja. Bersedia menawarkan bantuan & dukungan. Bertindak sebagai anggota Team yang dapat diandalkan dan memberikan kontribusi terhadap pencapaian keberhasilan Team.', 'high' => 'Fokus pada keberhasilan organisasi diatas keberhasilan pribadi. Mengutamakan efektivitas, kebersamaan dan morale Team. Dapat dijadikan teladan bagi team nya.'],
            ['label' => 'Inisiatif', 'low' => 'Pasif; kurang ada inisiatif, perhatian yang terbatas dan tidak mengenali masalah yang potensial.', 'standard' => 'Cepat memberikan response terhadap pelayanan. Mengenali potensi masalah dari pekerjaannya. Berupaya mencari alternative pemecahan masalah.', 'high' => 'Proaktif dan berupaya untuk menyiapkan solusi untuk masalah yang potensial. Memberikan bantuan dalam mencari pemecahan masalah.'],
            ['label' => 'Kepemimpinan', 'low' => 'Kurang mampu atau lemah didalam mengarahkan, mempengaruhi atau memotivasi anak buahnya untuk mencapai keberhasilan organisasi.', 'standard' => 'Mampu mengarahkan, mempengaruhi atau memotivasi anak buahnya untuk mencapai keberhasilan organisasi.', 'high' => 'Menunjukkan kemampuan yang tinggi atau dapat dijadikan teladan didalam mengarahkan, mempengaruhi atau memotivasi anak buahnya untuk mencapai tujuan organisasi.'],
        ];
    }

    private function identity($sheet, array $left, array $right): void
    {
        $p = $this->participant; $this->set($sheet, $left[0], $p->karyawan?->nama ?? '-'); $this->setExplicit($sheet, $left[1], $p->karyawan?->nik ?? '-'); $this->set($sheet, $left[2], $p->jabatan_snapshot ?: '-'); foreach (array_combine($right, [$p->departemen_snapshot ?: '-', $p->penempatan_snapshot ?: '-', $p->atasan_langsung_snapshot ?: '-']) as $cell => $value) $this->set($sheet, $cell, $value);
    }

    private function signatureSlot($sheet, string $placeholderCell, string $nameCell, string $dateCell, string $statusCell, ?KpiSignature $signature, ?string $fallbackStatus, ?string $statusOverride = null): void
    {
        $this->set($sheet, $nameCell, $signature?->signedBy?->karyawan?->nama ?? $signature?->signedBy?->name ?? ($signature ? '-' : 'Belum ditandatangani')); $this->set($sheet, $dateCell, $signature?->signed_at ? $signature->signed_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y H:i').' WIB' : '-'); $this->set($sheet, $statusCell, $statusOverride ?: ($signature ? $this->signatureStatus($signature) : ($fallbackStatus ?: 'Tanda tangan digital tidak tersedia')));
        $path = $signature ? $this->storagePath($signature->signature_path) : null; $path = $path ? $this->trimSignatureImage($path) : null; $this->set($sheet, $placeholderCell, $path ? '' : 'Tanda tangan digital tidak tersedia'); if ($path) $this->drawing($sheet, $path, $placeholderCell, 76, 'Tanda tangan');
    }

    private function dailySignatureSlot($sheet, int $signRow, KpiDailyReport $report, ?KpiDailyReport $signature): void
    {
        $this->set($sheet, "A{$signRow}", 'TANDA TANGAN DAILY REPORT'); $this->set($sheet, 'A'.($signRow + 1), $report->approval_source === 'super_admin_takeover' ? 'DIALIHKAN OLEH SUPER ADMIN' : 'ATASAN LANGSUNG'); $this->set($sheet, 'A'.($signRow + 2), $signature ? '' : 'Tanda tangan digital tidak tersedia'); $this->set($sheet, 'A'.($signRow + 6), $report->approver?->karyawan?->nama ?? $report->approver?->name ?? $report->atasanSnapshot?->nama ?? '-'); $this->set($sheet, 'A'.($signRow + 7), $report->approved_at ? $report->approved_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : '-'); $this->set($sheet, 'A'.($signRow + 8), $this->dailyStatus($report));
        if ($path = $signature ? $this->storagePath($report->approval_signature_path) : null) { $path = $this->trimSignatureImage($path); if ($path) $this->drawing($sheet, $path, 'A'.($signRow + 2), 76, 'Tanda tangan Daily Report'); }
    }

    private function clearDailySignature($sheet, int $row): void { foreach ([0, 1, 2, 6, 7, 8] as $offset) $this->set($sheet, 'A'.($row + $offset), ''); }
    private function drawing($sheet, string $path, string $cell, int $height, string $name): void
    {
        $drawing = new Drawing(); $drawing->setName($name); $drawing->setDescription($name); $drawing->setPath($path); $drawing->setHeight($height); $drawing->setCoordinates($cell);
        [$offsetX, $offsetY] = $this->centerDrawingOffsets($sheet, $cell, $path, $height);
        $drawing->setOffsetX($offsetX); $drawing->setOffsetY($offsetY); $drawing->setWorksheet($sheet);
    }

    private function centerDrawingOffsets($sheet, string $cell, string $path, int $height): array
    {
        [$startCol, $startRow, $endCol, $endRow] = $this->drawingRange($sheet, $cell);
        if ($slot = $this->signatureSlotColumns($sheet, $cell)) { $startCol = $slot[0]; $endCol = $slot[1]; }
        $width = 0; for ($column = $startCol; $column <= $endCol; $column++) { $columnWidth = (float) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->getWidth(); $width += ($columnWidth > 0 ? $columnWidth : 8.43) * 7; }
        $boxHeight = 0; for ($row = $startRow; $row <= $endRow; $row++) $boxHeight += max(15, (float) $sheet->getRowDimension($row)->getRowHeight()) * 4 / 3;
        $size = @getimagesize($path); $imageWidth = $size && $size[1] > 0 ? $height * $size[0] / $size[1] : $height;
        return [(int) max(0, ($width - $imageWidth) / 2), (int) max(0, ($boxHeight - $height) / 2)];
    }

    private function signatureSlotColumns($sheet, string $cell): ?array
    {
        [$column] = Coordinate::coordinateFromString($cell);
        $map = match ($sheet->getTitle()) {
            'Monthly' => ['A' => ['A', 'C'], 'D' => ['D', 'F'], 'H' => ['H', 'J'], 'L' => ['L', 'N']],
            'Kinerja Individu' => ['A' => ['A', 'E']],
            'Kinerja OPS' => ['A' => ['A', 'F'], 'G' => ['G', 'K']],
            'Nilai Akhir' => ['A' => ['A', 'F']],
            default => str_starts_with($sheet->getTitle(), 'DR ') ? ['A' => ['A', 'G']] : [],
        };
        if (! isset($map[$column])) return null;
        return [Coordinate::columnIndexFromString($map[$column][0]), Coordinate::columnIndexFromString($map[$column][1])];
    }

    private function drawingRange($sheet, string $cell): array
    {
        [$column, $row] = Coordinate::coordinateFromString($cell); $startCol = $endCol = Coordinate::columnIndexFromString($column); $startRow = $endRow = (int) $row;
        foreach ($sheet->getMergeCells() as $range) { [$from, $to] = array_pad(explode(':', $range), 2, $range); if (! $this->cellInRange($cell, $from, $to)) continue; [$fromColumn, $fromRow] = Coordinate::coordinateFromString($from); [$toColumn, $toRow] = Coordinate::coordinateFromString($to); $startCol = Coordinate::columnIndexFromString($fromColumn); $startRow = (int) $fromRow; $endCol = Coordinate::columnIndexFromString($toColumn); $endRow = (int) $toRow; break; }
        return [$startCol, $startRow, $endCol, $endRow];
    }

    private function cellInRange(string $cell, string $from, string $to): bool
    {
        [$column, $row] = Coordinate::coordinateFromString($cell); [$fromColumn, $fromRow] = Coordinate::coordinateFromString($from); [$toColumn, $toRow] = Coordinate::coordinateFromString($to);
        $column = Coordinate::columnIndexFromString($column); $fromColumn = Coordinate::columnIndexFromString($fromColumn); $toColumn = Coordinate::columnIndexFromString($toColumn);
        return $column >= $fromColumn && $column <= $toColumn && (int) $row >= (int) $fromRow && (int) $row <= (int) $toRow;
    }
    private function preferredSignature(Collection $signatures, array $roles): ?KpiSignature { foreach ($roles as $role) if ($signature = $signatures->firstWhere('role', $role)) return $signature; return $signatures->first(); }
    private function signatureFor(Collection $signatures, string $role): ?KpiSignature { return $signatures->firstWhere('role', $role); }
    private function signatureStatus(KpiSignature $signature): string { return $signature->source === 'super_admin_takeover' ? 'DIALIHKAN' : ($signature->role === 'employee' ? 'Ditandatangani Karyawan' : 'Disetujui Atasan'); }
    private function monthlyStatusLabel(?string $status): string { return match ($status) { 'completed', 'published' => 'Selesai', 'HRD_INCOMPLETE' => 'Menunggu HRD', 'draft' => 'Draft', default => $status ?: 'Belum tersedia' }; }
    private function dailyStatus(KpiDailyReport $report): string
    {
        if ($report->approval_source === 'super_admin_takeover') return '●  Dialihkan';
        if ($report->status === 'waiting_approval' && ! $report->approved_at) return '●  Menunggu Approval';
        if ($report->approved_at || $report->approved_by || $report->approval_signature_path) return '●  Disetujui Atasan';
        return '●  Menunggu Approval';
    }
    private function storagePath(?string $path): ?string { if (! $path) return null; $path = preg_replace('#^/?storage/#', '', trim($path)); return Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : (is_file($path) ? $path : null); }
    private function thumbnail(string $path): ?string { $size = @getimagesize($path); if (! $size || ! function_exists('imagecreatetruecolor')) return $path; [$width, $height] = $size; $scale = min(180 / max(1, $width), 125 / max(1, $height), 1); $create = match ($size['mime'] ?? '') { 'image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp', default => null }; if (! $create || ! function_exists($create)) return $path; $source = @$create($path); if (! $source) return $path; $canvas = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))); imagecopyresampled($canvas, $source, 0, 0, 0, 0, imagesx($canvas), imagesy($canvas), $width, $height); $target = tempnam(sys_get_temp_dir(), 'kpi_thumb_').'.jpg'; imagejpeg($canvas, $target, 78); imagedestroy($canvas); imagedestroy($source); $this->temporaryImages[] = $target; return $target; }
    private function percentValue($value): float|string { if ($value === null || $value === '') return '-'; $number = (float) $value; return $number > 1 ? $number / 100 : $number; }
    private function setExplicit($sheet, string $cell, $value): void { $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING); }
    private function set($sheet, string $cell, $value): void { $sheet->setCellValue($cell, $value); }
    private function periodStart(): Carbon { return Carbon::create($this->period->tahun, $this->period->bulan, 1, 0, 0, 0, 'Asia/Jakarta'); }
    private function periodEnd(): Carbon { return $this->periodStart()->endOfMonth(); }
    private function monthName(): string { return $this->periodStart()->locale('id')->translatedFormat('F'); }
    private function periodLabel(): string { return $this->periodStart()->locale('id')->translatedFormat('F Y'); }
}
