<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import { useConfirmation } from '@/composables/useConfirmation';

const props = defineProps({
    user: Object,
    period: Object,
    eligibleEvaluators: { type: Array, default: () => [] },
    assignedEvaluatorId: [Number, String],
    assignedEvaluatorName: String,
    isAssignedEvaluator: Boolean,
    isWindowOpen: Boolean,
    isBlocked: Boolean,
    assignmentLocked: Boolean,
    isFormView: Boolean,
    isHrdOrDirektur: Boolean,
    participants: { type: Array, default: () => [] },
    selectedParticipant: Object,
    hasSubordinatesSnapshot: Boolean,
    isRatingSelf: Boolean,
    monthly: Object,
    hrdData: { type: Object, default: () => ({ attendance: [], rewards: [] }) },
    summary: { type: Object, default: () => ({}) },
});

const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const periodLabel = computed(() => `${months[(props.period?.bulan || 1) - 1]} ${props.period?.tahun || ''}`);
const inputPeriodLabel = computed(() => { const date = new Date(props.period.tahun, props.period.bulan, 1); return `1–5 ${months[date.getMonth()]} ${date.getFullYear()}`; });
const isSuperAdmin = computed(() => props.user?.roleName === 'super_admin');
const confirmation = useConfirmation();
const canEdit = computed(() => { if (['completed', 'published'].includes(props.monthly?.status)) return false; return props.isHrdOrDirektur || (props.isAssignedEvaluator && props.isWindowOpen); });
const canFinalize = computed(() => props.isHrdOrDirektur && !['completed', 'published'].includes(props.monthly?.status));
const narrativeComplete = computed(() => Boolean(String(form.performance || '').trim() && String(form.coaching || '').trim()));

const assignForm = useForm({ evaluator_id: props.assignedEvaluatorId || '' });
const submitAssign = () => assignForm.post(route('dashboard.kpi.mpa.assign', props.period.id), { preserveScroll: true });
const form = useForm({
    action: 'draft', confirm_hrd: false, karyawan_id: props.selectedParticipant?.karyawan_id || '',
    kinerja_operasional: props.monthly?.kinerja_operasional ?? null,
    sikap_kerja: props.monthly?.sikap_kerja ?? null, team_work: props.monthly?.team_work ?? null,
    inisiatif: props.monthly?.inisiatif ?? null, kepemimpinan: props.monthly?.kepemimpinan ?? null,
    performance: props.monthly?.performance ?? '', coaching: props.monthly?.coaching ?? '', takeover_reason: '',
});
const attendanceRows = [
    { kode: 'P1', label: 'Urusan Pribadi', rate: 0.5 },
    { kode: 'DL', label: 'Datang Lambat', rate: 0.3 },
    { kode: 'PC', label: 'Pulang Cepat', rate: 0.3 },
    { kode: 'LC', label: 'Lupa Catat', rate: 0.3 },
    { kode: 'M', label: 'Mangkir', rate: 3 },
];
const rewardRows = [
    { jenis: 'major_award', label: 'Jasa Besar (Major Award)', rate: 7 },
    { jenis: 'minor_award', label: 'Jasa Kecil (Minor Award)', rate: 3 },
    { jenis: 'minor_demerit', label: 'Kesalahan Ringan (Minor Demerit)', rate: -4 },
    { jenis: 'major_demerit', label: 'Kesalahan Besar (Major Demerit)', rate: -8 },
];
const hrdForm = useForm({
    monthly_id: props.monthly?.id || '',
    adjustments: attendanceRows.map((row) => ({ kode: row.kode, jumlah: props.hrdData?.attendance?.find((item) => item.kode === row.kode)?.jumlah ?? 0 })),
    rewards: rewardRows.map((row) => ({ jenis: row.jenis, jumlah: props.hrdData?.rewards?.find((item) => item.jenis === row.jenis)?.jumlah ?? 0 })),
});
const attendanceTotal = (index) => (Number(hrdForm.adjustments[index]?.jumlah) || 0) * attendanceRows[index].rate;
const attendanceSum = computed(() => hrdForm.adjustments.reduce((sum, _row, index) => sum + attendanceTotal(index), 0));
const attendanceScore = computed(() => ((10 - attendanceSum.value) * 0.5).toFixed(2));
const rewardTotal = (index) => (Number(hrdForm.rewards[index]?.jumlah) || 0) * rewardRows[index].rate;
const rewardSum = computed(() => hrdForm.rewards.reduce((sum, _row, index) => sum + rewardTotal(index), 0));
const saveHrdData = () => hrdForm.post(route('dashboard.kpi.monthly.save', props.period.id), { preserveScroll: true });

const rangeText = {
    low: { min: 1, max: 15, range: '1–15', title: 'Di bawah rata-rata' },
    standard: { min: 16, max: 30, range: '16–30', title: 'Mencapai target / standar' },
    high: { min: 31, max: 45, range: '31–45', title: 'Luar biasa' },
};
const operationalDimension = {
    key: 'kinerja_operasional', label: 'Kinerja Operasional', description: 'Pencapaian target dan standar pekerjaan harian.',
    criteria: [
        { ...rangeText.low, description: 'Di bawah rata-rata — pencapaian target dan standar pekerjaan harian.' },
        { ...rangeText.standard, description: 'Mencapai target / standar — pencapaian target dan standar pekerjaan harian.' },
        { ...rangeText.high, description: 'Luar biasa — pencapaian target dan standar pekerjaan harian.' },
    ],
};
const generalDimensions = computed(() => [
    { key: 'sikap_kerja', label: 'Sikap Kerja', description: 'Antusiasme dan kesungguhan dalam bekerja.', criteria: [
        { ...rangeText.low, description: 'Di bawah rata-rata — antusiasme dan kesungguhan dalam bekerja.' },
        { ...rangeText.standard, description: 'Mencapai target / standar — antusiasme dan kesungguhan dalam bekerja.' },
        { ...rangeText.high, description: 'Luar biasa — antusiasme dan kesungguhan dalam bekerja.' },
    ] },
    { key: 'team_work', label: 'Team Work', description: 'Kerja sama dan kontribusi dalam tim.', criteria: [
        { ...rangeText.low, description: 'Di bawah rata-rata — kerja sama dan kontribusi dalam tim.' },
        { ...rangeText.standard, description: 'Mencapai target / standar — kerja sama dan kontribusi dalam tim.' },
        { ...rangeText.high, description: 'Luar biasa — kerja sama dan kontribusi dalam tim.' },
    ] },
    { key: 'inisiatif', label: 'Inisiatif', description: 'Inisiatif dan sikap proaktif dalam menyelesaikan masalah.', criteria: [
        { ...rangeText.low, description: 'Di bawah rata-rata — inisiatif dan sikap proaktif dalam menyelesaikan masalah.' },
        { ...rangeText.standard, description: 'Mencapai target / standar — inisiatif dan sikap proaktif dalam menyelesaikan masalah.' },
        { ...rangeText.high, description: 'Luar biasa — inisiatif dan sikap proaktif dalam menyelesaikan masalah.' },
    ] },
    ...(props.hasSubordinatesSnapshot ? [{ key: 'kepemimpinan', label: 'Kepemimpinan / Potensi Kepemimpinan', description: 'Kemampuan memengaruhi dan mengarahkan.', criteria: [
        { ...rangeText.low, description: 'Di bawah rata-rata — kemampuan memengaruhi dan mengarahkan.' },
        { ...rangeText.standard, description: 'Mencapai target / standar — kemampuan memengaruhi dan mengarahkan.' },
        { ...rangeText.high, description: 'Luar biasa — kemampuan memengaruhi dan mengarahkan.' },
    ] }] : []),
]);
const allDimensions = computed(() => [operationalDimension, ...generalDimensions.value]);
const selectedRating = (key) => { const value = Number(form[key]); return Number.isInteger(value) && value >= 1 && value <= 45 ? value : null; };
const ratingPercent = (key) => `${Math.round(((selectedRating(key) ?? 0) / 45) * 100)}%`;
const setRating = (key, value) => { if (value === '' || value === null || value === undefined) { form[key] = null; return; } const parsed = Number.parseInt(value, 10); form[key] = Number.isNaN(parsed) ? null : Math.min(45, Math.max(1, parsed)); };
const isActiveRange = (key, criterion) => { const value = selectedRating(key); return value !== null && value >= criterion.min && value <= criterion.max; };
const calculatedScore = computed(() => { const values = allDimensions.value.map((dimension) => selectedRating(dimension.key)); if (values.some((value) => value === null)) return '–'; return (((values.reduce((total, value) => total + value, 0) / values.length) / 45) * 5).toFixed(2); });
const openForm = (id) => router.get(route('dashboard.kpi.mpa', props.period.id), { karyawan_id: id });
const saveAssessment = async (action) => {
    if (action === 'draft' && !narrativeComplete.value) {
        if (!String(form.performance || '').trim()) form.setError('performance', 'Bagian ini wajib diisi sebelum draft disimpan.');
        if (!String(form.coaching || '').trim()) form.setError('coaching', 'Bagian ini wajib diisi sebelum draft disimpan.');
        return;
    }
    if (action === 'complete' && !props.monthly?.completed_at) {
        const confirmed = await confirmation.confirm({
            type: 'warning',
            title: 'Konfirmasi Penyelesaian MPA',
            message: 'Data dari HRD belum tercatat untuk karyawan ini.',
            description: 'Lanjutkan jika memang tidak ada komponen HRD yang perlu diisi.',
            confirmText: 'Ya, Selesaikan MPA',
        });
        if (!confirmed) return;
        form.confirm_hrd = true;
    } else {
        form.confirm_hrd = false;
    }
    form.action = action;
    // Do not preserve the previous page state after saving; the list and
    // summary must be rebuilt from the freshly persisted Monthly record.
    form.post(route('dashboard.kpi.mpa', props.period.id), { preserveScroll: true, preserveState: false });
};
const statusLabel = (status) => ({ scheduled: 'Belum Dinilai', draft: 'Dalam Proses', completed: 'Sudah Dinilai', published: 'Sudah Dinilai', takeover: 'HRD Takeover' }[status] || 'Menunggu Lanjutan HRD');
</script>

<template>
    <InternalDashboardLayout title="MPA" :user="user" content-width="wide">
        <div class="w-full space-y-5 p-6">
            <header class="flex flex-col justify-between gap-4 rounded-2xl border border-blue-100 bg-white p-6 shadow-sm md:flex-row md:items-start"><div><div class="mb-1 text-xs font-semibold text-[#53709d]">KPI <span class="mx-1">›</span> MPA<span v-if="isFormView"> <span class="mx-1">›</span> Beri Nilai</span></div><h1 class="text-3xl font-bold text-[#0b347d]">{{ isFormView ? 'Form Beri Nilai MPA' : 'MPA (Monthly Performance Appraisal)' }}</h1><p class="text-sm text-[#53709d]">{{ isFormView ? 'Lengkapi penilaian MPA untuk karyawan. Penilaian ini merupakan bagian dari record bulanan yang sama.' : 'Manajemen Penilaian Akhir (MPA)' }}</p></div><div class="rounded-xl bg-emerald-50 px-5 py-3 text-sm text-emerald-700"><strong>Periode Aktif</strong><div class="font-bold">{{ periodLabel }}</div></div></header>
            <template v-if="!isFormView">
                <section class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm"><div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end"><div><h2 class="text-lg font-bold text-[#0b347d]">👥 Penetapan Penilai Bulanan</h2><p class="text-xs text-[#53709d]">Satu periode memiliki satu penilai utama. Periode pengisian: {{ inputPeriodLabel }}.</p></div><form v-if="isSuperAdmin" @submit.prevent="submitAssign" class="flex gap-2"><select v-model="assignForm.evaluator_id" :disabled="assignmentLocked" class="rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs"><option value="" disabled>Pilih karyawan penilai</option><option v-for="ev in eligibleEvaluators" :key="ev.id" :value="ev.id">{{ ev.position ? `${ev.name} (${ev.position})` : ev.name }}</option></select><button :disabled="assignmentLocked || assignForm.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50">Tetapkan Penilai</button></form></div><div class="mt-4 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">Penilai utama: <strong>{{ assignedEvaluatorName }}</strong>. Penetapan terkunci setelah hari terakhir bulan performa.</div></section>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4"><div class="rounded-xl bg-blue-50 p-4"><div class="text-xs text-blue-700">Total Karyawan Dinilai</div><strong class="text-2xl text-[#0b347d]">{{ summary.total || 0 }}</strong></div><div class="rounded-xl bg-emerald-50 p-4"><div class="text-xs text-emerald-700">Sudah Dinilai</div><strong class="text-2xl text-emerald-700">{{ summary.completed || 0 }}</strong></div><div class="rounded-xl bg-amber-50 p-4"><div class="text-xs text-amber-700">Belum Dinilai</div><strong class="text-2xl text-amber-700">{{ summary.pending || 0 }}</strong></div><div class="rounded-xl bg-violet-50 p-4"><div class="text-xs text-violet-700">Menunggu Lanjutan HRD</div><strong class="text-2xl text-violet-700">{{ summary.hrd || 0 }}</strong></div></div>
                <section class="overflow-hidden rounded-xl border border-blue-100 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-blue-100 p-5"><div><h2 class="text-xl font-bold text-[#0b347d]">▤ Daftar Karyawan</h2><p class="text-xs text-[#53709d]">Pilih karyawan untuk memberikan atau melihat penilaian MPA.</p></div><span class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700">{{ participants.length }} karyawan</span></div><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-[#f3f7fd] text-left text-xs font-bold text-[#53709d]"><tr><th class="px-4 py-3">No</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Jabatan</th><th class="px-4 py-3">Departemen</th><th class="px-4 py-3">Penempatan</th><th class="px-4 py-3">Status Penilaian</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead><tbody><tr v-for="(participant, index) in participants" :key="participant.id" class="border-t border-blue-50"><td class="px-4 py-3 text-[#53709d]">{{ index + 1 }}</td><td class="px-4 py-3 font-bold text-[#173f82]">{{ participant.nama }}</td><td class="px-4 py-3 text-[#53709d]">{{ participant.jabatan }}</td><td class="px-4 py-3 text-[#53709d]">{{ participant.departemen }}</td><td class="px-4 py-3 text-[#53709d]">{{ participant.penempatan || '-' }}</td><td class="px-4 py-3"><span :class="participant.status === 'completed' || participant.status === 'published' ? 'bg-emerald-100 text-emerald-700' : participant.status === 'takeover' ? 'bg-violet-100 text-violet-700' : 'bg-amber-100 text-amber-700'" class="rounded-full px-2.5 py-1 text-xs font-bold">{{ statusLabel(participant.status) }}</span></td><td class="px-4 py-3 text-right"><button @click="openForm(participant.karyawan_id)" class="rounded-lg px-3 py-2 text-xs font-bold" :class="participant.status === 'completed' || participant.status === 'published' ? 'border border-slate-200 text-slate-500' : 'bg-blue-600 text-white'">{{ participant.status === 'completed' || participant.status === 'published' ? 'Dinilai' : 'Beri Nilai' }}</button></td></tr></tbody></table></div></section>
            </template>
            <template v-else>
                <section class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm"><div class="mb-3 flex items-center justify-between gap-3"><h2 class="text-lg font-bold text-[#0b347d]">👤 Informasi Karyawan</h2><span class="rounded-full px-3 py-1 text-xs font-bold" :class="monthly?.status === 'completed' || monthly?.status === 'published' ? 'bg-emerald-100 text-emerald-700' : monthly?.status === 'draft' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'">{{ statusLabel(monthly?.status) }}</span></div><div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4"><div v-for="field in [['Nama', selectedParticipant?.karyawan?.nama], ['NIK', selectedParticipant?.karyawan?.nik], ['Jabatan', selectedParticipant?.jabatan_snapshot], ['Departemen', selectedParticipant?.departemen_snapshot], ['Penempatan', selectedParticipant?.penempatan_snapshot], ['Atasan Langsung', selectedParticipant?.atasan_langsung_snapshot], ['Atasan Kedua', selectedParticipant?.atasan_kedua_snapshot || 'N/A'], ['Periode', periodLabel]]" :key="field[0]" class="rounded-lg bg-[#f3f7fd] px-3 py-2"><div class="text-[11px] text-[#6480aa]">{{ field[0] }}</div><strong class="text-sm text-[#173f82]">{{ field[1] || '-' }}</strong></div></div></section>
                <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-800">Penilaian umum diisi penilai. Absensi dan Reward/Punishment tetap menjadi bagian HRD pada record Monthly yang sama.</div>
                <form @submit.prevent="saveAssessment('draft')" class="space-y-5">
                    <section class="space-y-3"><div><h2 class="text-xl font-bold text-[#0b347d]">1. Kinerja Operasional</h2><p class="mt-1 text-xs text-[#53709d]">Pilih nilai berdasarkan kriteria yang selalu ditampilkan di bawah ini.</p></div><article class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="text-lg font-bold text-[#173f82]">{{ operationalDimension.label }}</h3><p class="mt-1 text-sm text-[#60789f]">{{ operationalDimension.description }}</p></div><div class="shrink-0 rounded-lg bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-800">Nilai: <strong class="text-xl">{{ selectedRating(operationalDimension.key) ?? '–' }}</strong> / 45</div></div><div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3"><div v-for="criterion in operationalDimension.criteria" :key="criterion.range" :class="isActiveRange(operationalDimension.key, criterion) ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-200' : 'border-slate-200 bg-slate-50'" class="rounded-xl border p-4 transition"><div class="flex items-center justify-between gap-2"><span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-[#173f82]">{{ criterion.range }}</span><span v-if="isActiveRange(operationalDimension.key, criterion)" class="text-xs font-bold text-blue-700">Terpilih</span></div><h4 class="mt-3 text-sm font-bold text-[#173f82]">{{ criterion.title }}</h4><p class="mt-1 text-xs leading-5 text-slate-600">{{ criterion.description }}</p></div></div><div class="mt-5"><div class="mb-2 flex items-center justify-between text-xs font-semibold text-slate-600"><span>Pilih nilai penilaian</span><span>1–45</span></div><div class="grid grid-cols-5 gap-1 sm:grid-cols-9 lg:grid-cols-[repeat(15,minmax(0,1fr))]"><button v-for="n in 45" :key="n" type="button" :disabled="!canEdit" :class="selectedRating(operationalDimension.key) === n ? 'bg-blue-600 text-white' : 'bg-[#f3f7fd] text-[#173f82]'" class="rounded border border-blue-100 py-1.5 text-[10px] font-bold transition hover:border-blue-400 disabled:cursor-not-allowed disabled:opacity-60" @click="setRating(operationalDimension.key, n)">{{ n }}</button></div></div><p v-if="form.errors.kinerja_operasional" class="mt-2 text-xs font-semibold text-rose-600">{{ form.errors.kinerja_operasional }}</p></article></section>
                    <section class="space-y-3"><div><h2 class="text-xl font-bold text-[#0b347d]">2. Penilaian Umum</h2><p class="mt-1 text-xs text-[#53709d]">Nilai setiap dimensi berdasarkan deskripsi rentang yang sesuai.</p></div><article v-for="(dimension, index) in generalDimensions" :key="dimension.key" class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><div class="text-xs font-bold uppercase tracking-wide text-blue-600">{{ String.fromCharCode(65 + index) }}. Dimensi</div><h3 class="mt-1 text-lg font-bold text-[#173f82]">{{ dimension.label }}</h3><p class="mt-1 text-sm text-[#60789f]">{{ dimension.description }}</p></div><div class="shrink-0 rounded-lg bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-800">Nilai: <strong class="text-xl">{{ selectedRating(dimension.key) ?? '–' }}</strong> / 45</div></div><div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3"><div v-for="criterion in dimension.criteria" :key="criterion.range" :class="isActiveRange(dimension.key, criterion) ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-200' : 'border-slate-200 bg-slate-50'" class="rounded-xl border p-4 transition"><div class="flex items-center justify-between gap-2"><span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-[#173f82]">{{ criterion.range }}</span><span v-if="isActiveRange(dimension.key, criterion)" class="text-xs font-bold text-blue-700">Terpilih</span></div><h4 class="mt-3 text-sm font-bold text-[#173f82]">{{ criterion.title }}</h4><p class="mt-1 text-xs leading-5 text-slate-600">{{ criterion.description }}</p></div></div><div class="mt-5"><div class="mb-2 flex items-center justify-between text-xs font-semibold text-slate-600"><span>Pilih nilai penilaian</span><span>1–45</span></div><div class="grid grid-cols-5 gap-1 sm:grid-cols-9 lg:grid-cols-[repeat(15,minmax(0,1fr))]"><button v-for="n in 45" :key="n" type="button" :disabled="!canEdit" :class="selectedRating(dimension.key) === n ? 'bg-blue-600 text-white' : 'bg-[#f3f7fd] text-[#173f82]'" class="rounded border border-blue-100 py-1.5 text-[10px] font-bold transition hover:border-blue-400 disabled:cursor-not-allowed disabled:opacity-60" @click="setRating(dimension.key, n)">{{ n }}</button></div></div><p v-if="form.errors[dimension.key]" class="mt-2 text-xs font-semibold text-rose-600">{{ form.errors[dimension.key] }}</p></article></section>                    <section class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-stretch">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div><h2 class="text-xl font-bold text-[#0b347d]">3. Nilai Penilaian Umum</h2><p class="mt-1 text-xs text-[#60789f]">Ringkasan nilai setiap dimensi yang digunakan dalam perhitungan penilaian umum.</p></div>
                                    <span class="hidden rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 sm:inline-flex">Skala 1–45</span>
                                </div>
                                <div class="mt-4 grid gap-2">
                                    <div v-for="dimension in allDimensions" :key="dimension.key" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                        <div class="flex items-center justify-between gap-3"><span class="text-sm font-semibold text-[#173f82]">{{ dimension.label }}</span><span class="rounded-lg bg-white px-2.5 py-1 text-sm font-bold text-[#173f82]">{{ selectedRating(dimension.key) ?? '–' }} <span class="text-xs font-normal text-slate-500">/ 45</span></span></div>
                                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-blue-600 transition-all" :style="{ width: ratingPercent(dimension.key) }"></div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex min-w-[220px] flex-col justify-center rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-green-100 px-6 py-6 text-center shadow-sm">
                                <span class="text-xs font-bold uppercase tracking-wide text-emerald-700">Nilai Penilaian Umum</span>
                                <strong class="mt-2 text-5xl leading-none text-emerald-700">{{ calculatedScore }}</strong>
                                <span class="mt-2 text-sm font-semibold text-emerald-800">dari 5</span>
                                <span class="mx-auto mt-4 rounded-full bg-white/80 px-3 py-1 text-[11px] font-medium text-emerald-700">Hasil perhitungan MPA</span>
                            </div>
                        </div>
                    </section>                    <section class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm">
                        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"><div><h2 class="text-xl font-bold text-[#0b347d]">4. Data dari HRD</h2><p class="mt-1 text-xs text-[#60789f]">Penilaian absensi dan reward/punishment dilengkapi oleh HRD pada record Monthly yang sama.</p></div><span v-if="isHrdOrDirektur" class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Mode HRD</span><span v-else class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Read-only</span></div>
                        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                            <div class="overflow-hidden rounded-xl border border-blue-100"><div class="bg-[#eef4fb] px-4 py-3 text-sm font-bold text-[#173f82]">Penilaian Absensi</div><div class="overflow-x-auto"><table class="w-full text-xs"><thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-3 py-2">Kriteria</th><th class="px-3 py-2 text-center">Kode</th><th class="px-3 py-2 text-center">Potongan</th><th class="px-3 py-2 text-center">Jumlah Hari</th><th class="px-3 py-2 text-right">Total</th></tr></thead><tbody><tr v-for="(row, index) in attendanceRows" :key="row.kode" class="border-t border-blue-50"><td class="px-3 py-2 text-[#173f82]">{{ row.label }}</td><td class="px-3 py-2 text-center text-slate-500">{{ row.kode }}</td><td class="px-3 py-2 text-center text-slate-600">{{ row.rate }}</td><td class="px-3 py-2 text-center"><input v-if="isHrdOrDirektur" v-model.number="hrdForm.adjustments[index].jumlah" type="number" min="0" step="1" class="w-20 rounded border border-blue-200 px-2 py-1 text-center text-xs"/><span v-else class="text-slate-600">{{ hrdData?.attendance?.find((item) => item.kode === row.kode)?.jumlah || 0 }}</span></td><td class="px-3 py-2 text-right font-semibold text-[#173f82]">{{ attendanceTotal(index).toFixed(2) }}</td></tr></tbody><tfoot><tr class="border-t border-blue-100 bg-slate-50"><td colspan="4" class="px-3 py-2 text-right font-bold text-[#173f82]">Nilai Absensi</td><td class="px-3 py-2 text-right font-bold text-emerald-700">{{ attendanceScore }}</td></tr></tfoot></table></div></div>
                            <div class="overflow-hidden rounded-xl border border-blue-100"><div class="bg-[#eef4fb] px-4 py-3 text-sm font-bold text-[#173f82]">Penambahan atau Pengurangan Nilai</div><div class="overflow-x-auto"><table class="w-full text-xs"><thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-3 py-2">Kriteria</th><th class="px-3 py-2 text-center">Angka</th><th class="px-3 py-2 text-center">Jumlah</th><th class="px-3 py-2 text-right">Total</th></tr></thead><tbody><tr v-for="(row, index) in rewardRows" :key="row.jenis" class="border-t border-blue-50"><td class="px-3 py-2 text-[#173f82]">{{ row.label }}</td><td class="px-3 py-2 text-center text-slate-600">{{ row.rate > 0 ? '+' : '' }}{{ row.rate }}</td><td class="px-3 py-2 text-center"><input v-if="isHrdOrDirektur" v-model.number="hrdForm.rewards[index].jumlah" type="number" min="0" step="1" class="w-20 rounded border border-blue-200 px-2 py-1 text-center text-xs"/><span v-else class="text-slate-600">{{ hrdData?.rewards?.find((item) => item.jenis === row.jenis)?.jumlah || 0 }}</span></td><td class="px-3 py-2 text-right font-semibold text-[#173f82]">{{ rewardTotal(index).toFixed(2) }}</td></tr></tbody><tfoot><tr class="border-t border-blue-100 bg-slate-50"><td colspan="3" class="px-3 py-2 text-right font-bold text-[#173f82]">Total</td><td class="px-3 py-2 text-right font-bold text-emerald-700">{{ rewardSum.toFixed(2) }}</td></tr></tfoot></table></div></div>
                        </div>
                        <p v-if="isHrdOrDirektur && !monthly?.completed_at" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">Data HRD belum tercatat. Jika komponen HRD memang tidak perlu diisi, konfirmasi akan ditampilkan saat Anda menyelesaikan MPA.</p>
                    </section>
                    <section class="grid grid-cols-1 gap-4 lg:grid-cols-2"><div class="rounded-xl border border-blue-100 bg-white p-4"><h2 class="text-lg font-bold text-[#0b347d]">5. Penjelasan Berkaitan Dengan Performance</h2><textarea v-model="form.performance" :disabled="!canEdit" rows="4" :class="form.errors.performance ? 'border-rose-400 ring-1 ring-rose-200' : 'border-blue-100'" class="mt-3 w-full rounded-lg border p-3 text-xs disabled:bg-slate-50"></textarea><p v-if="form.errors.performance" class="mt-2 flex items-center gap-1 text-xs font-semibold text-rose-600"><span aria-hidden="true">⚠</span>{{ form.errors.performance }}</p></div><div class="rounded-xl border border-blue-100 bg-white p-4"><h2 class="text-lg font-bold text-[#0b347d]">6. Rencana Perbaikan (coaching, counseling)</h2><textarea v-model="form.coaching" :disabled="!canEdit" rows="4" :class="form.errors.coaching ? 'border-rose-400 ring-1 ring-rose-200' : 'border-blue-100'" class="mt-3 w-full rounded-lg border p-3 text-xs disabled:bg-slate-50"></textarea><p v-if="form.errors.coaching" class="mt-2 flex items-center gap-1 text-xs font-semibold text-rose-600"><span aria-hidden="true">⚠</span>{{ form.errors.coaching }}</p></div></section>
                    <div class="flex flex-wrap items-center justify-end gap-2"><button type="button" @click="router.get(route('dashboard.kpi.mpa', period.id))" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600">Kembali</button><button type="submit" :disabled="!canEdit || form.processing" class="rounded-lg border border-blue-600 px-4 py-2 text-xs font-bold text-blue-700 disabled:opacity-50">Simpan Draft</button><button v-if="canFinalize" type="button" :disabled="form.processing || !narrativeComplete" @click="saveAssessment('complete')" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50">Selesaikan Penilaian MPA</button></div>
                </form>
            </template>
        </div>
    </InternalDashboardLayout>
</template>
