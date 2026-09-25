<script setup>
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import KpiEmployeeHeader from '@/Components/Internal/KpiEmployeeHeader.vue';
import KpiEmployeeLayout from '@/Components/Internal/KpiEmployeeLayout.vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    user: Object,
    period: Object,
    monthlies: Array,
    isPublishable: Boolean,
    isMonitoring: Boolean,
    monitoringEmployeeId: Number,
    viewMode: { type: String, default: 'hrd' },
    participant: Object,
    employeeHeader: Object,
    monthlyDetail: Object,
    signatures: { type: Object, default: () => ({}) },
    isOwner: Boolean,
    hasLeadershipDimension: Boolean,
    canSignEmployee: Boolean,
    canSignSupervisor: Boolean,
    canSignSecondSupervisor: Boolean,
    employeePeriods: { type: Array, default: () => [] },
});

const selectedMonthly = ref(props.monthlies?.[0] || null);

// Attendance rates per PRD
const rateMap = { P1: 0.5, DL: 0.3, PC: 0.3, LC: 0.3, M: 3.0 };

// Form for Attendance Adjustments & Reward/Punishment
const form = useForm({
    monthly_id: selectedMonthly.value?.id || '',
    adjustments: [
        { kode: 'P1', label: 'P1 (Urusan Pribadi = 0.5)', jumlah: 0 },
        { kode: 'DL', label: 'DL (Datang Lambat = 0.3)', jumlah: 0 },
        { kode: 'PC', label: 'PC (Pulang Cepat = 0.3)', jumlah: 0 },
        { kode: 'LC', label: 'LC (Lupa Catat = 0.3)', jumlah: 0 },
        { kode: 'M', label: 'M (Mangkir = 3.0)', jumlah: 0 },
    ],
    rewards: [
        { jenis: 'major_award', label: 'Major Award (+7.00)', jumlah: 0 },
        { jenis: 'minor_award', label: 'Minor Award (+3.00)', jumlah: 0 },
        { jenis: 'minor_demerit', label: 'Minor Demerit (-4.00)', jumlah: 0 },
        { jenis: 'major_demerit', label: 'Major Demerit (-8.00)', jumlah: 0 },
    ],
});

// Select monthly record to edit attendance/reward
const selectMonthly = (m) => {
    selectedMonthly.value = m;
    form.monthly_id = m.id;

    // Pre-fill adjustments
    form.adjustments.forEach(adj => {
        const existing = m.adjustments?.find(a => a.kode === adj.kode);
        adj.jumlah = existing ? existing.jumlah : 0;
    });

    // Pre-fill rewards
    form.rewards.forEach(rew => {
        const existing = m.rewards?.find(r => r.jenis === rew.jenis);
        rew.jumlah = existing ? existing.jumlah : 0;
    });
};

// Auto calculate attendance deduction & score (NO clamping!)
const calculatedDeduction = computed(() => {
    return form.adjustments.reduce((sum, adj) => {
        const rate = rateMap[adj.kode] || 0;
        return sum + (rate * (Number(adj.jumlah) || 0));
    }, 0);
});

const calculatedAttendanceScore = computed(() => {
    return ((10 - calculatedDeduction.value) * 0.5).toFixed(2);
});

// Auto calculate reward/punishment score (NO clamping!)
const rewardMap = { major_award: 7.0, minor_award: 3.0, minor_demerit: -4.0, major_demerit: -8.0 };
const calculatedRewardScore = computed(() => {
    return form.rewards.reduce((sum, rew) => {
        const val = rewardMap[rew.jenis] || 0;
        return sum + (val * (Number(rew.jumlah) || 0));
    }, 0).toFixed(2);
});

const saveMonthlyComponents = () => {
    form.post(route('dashboard.kpi.monthly.save', props.period.id), {
        preserveScroll: true,
    });
};

// Period-wide Publish action with confirmation
const publishModalOpen = ref(false);
const executePublish = () => {
    router.post(route('dashboard.kpi.monthly.publish', props.period.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            publishModalOpen.value = false;
        }
    });
};

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const periodLabel = computed(() => `${monthNames[props.period.bulan - 1]} ${props.period.tahun}`);

const isResultView = computed(() => props.viewMode === 'result');
const resultEmployee = computed(() => props.participant?.karyawan || {});
const resultEmployeeId = computed(() => Number(props.monitoringEmployeeId || props.participant?.karyawan_id || props.employeeHeader?.id || 0));
const resultMonthly = computed(() => props.monthlyDetail || {});
const resultStatus = computed(() => {
    const status = resultMonthly.value.status;
    if (status === 'published' || status === 'completed') {
        const required = ['hrd_publish', 'employee', 'atasan_langsung'];
        if (props.participant?.atasan_kedua_id) required.push('atasan_kedua');
        return required.every((role) => props.signatures?.[role]) ? 'Selesai' : (props.signatures?.hrd_publish ? 'Menunggu Tanda Tangan' : 'Menunggu Publish');
    }
    if (status === 'completed') return 'Menunggu Publish';
    if (status === 'HRD_INCOMPLETE') return 'Menunggu HRD';
    if (status === 'scheduled') return 'Belum Dinilai';
    return status || 'Belum Dinilai';
});
const leadershipScore = computed(() => props.hasLeadershipDimension ? resultMonthly.value.kepemimpinan : null);
const operationalScore = computed(() => Number(resultMonthly.value.kinerja_operasional || 0));
const commonScores = computed(() => [
    Number(resultMonthly.value.sikap_kerja || 0),
    Number(resultMonthly.value.team_work || 0),
    Number(resultMonthly.value.inisiatif || 0),
    ...(props.hasLeadershipDimension ? [Number(resultMonthly.value.kepemimpinan || 0)] : []),
]);
const normalizedGeneralScore = computed(() => {
    const values = [operationalScore.value, ...commonScores.value];
    const divisor = values.length || 1;
    return ((values.reduce((sum, value) => sum + value, 0) / divisor) / 45 * 5).toFixed(2);
});
const signatureCards = computed(() => [
    { key: 'hrd_publish', label: 'HRD', person: 'HRD / Direktur', canSign: false },
    { key: 'atasan_kedua', label: 'Atasan Ke-2', person: props.participant?.atasan_kedua_snapshot || 'Tidak tersedia', canSign: props.canSignSecondSupervisor },
    { key: 'atasan_langsung', label: 'Atasan Langsung', person: props.participant?.atasan_langsung_snapshot || 'Tidak tersedia', canSign: props.canSignSupervisor },
    { key: 'employee', label: 'Karyawan', person: resultEmployee.value.nama || '-', canSign: props.canSignEmployee },
]);
const signMonthly = () => {
    router.post(route('dashboard.kpi.sign'), {
        signable_type: 'monthly',
        signable_id: resultMonthly.value.id,
    }, { preserveScroll: true });
};
const adjustmentRows = computed(() => {
    const defaults = [
        ['Urusan Pribadi', 'P1', 0.5], ['Datang Lambat', 'DL', 0.3], ['Pulang Cepat', 'PC', 0.3],
        ['Lupa Catat', 'LC', 0.3], ['Mangkir', 'M', 3],
    ];
    return defaults.map(([label, kode, rate]) => {
        const item = resultMonthly.value.adjustments?.find((row) => row.kode === kode);
        return { label, kode, rate, jumlah: item?.jumlah || 0, total: item?.nilai || 0 };
    });
});
const rewardRows = computed(() => {
    const defaults = [
        ['Jasa Besar (Major Award)', 'major_award', 7], ['Jasa Kecil (Minor Award)', 'minor_award', 3],
        ['Kesalahan Ringan (Minor Demerit)', 'minor_demerit', -4], ['Kesalahan Besar (Major Demerit)', 'major_demerit', -8],
    ];
    return defaults.map(([label, jenis, rate]) => {
        const item = resultMonthly.value.rewards?.find((row) => row.jenis === jenis);
        return { label, jenis, rate, jumlah: item?.jumlah || 0, total: item?.nilai || 0 };
    });
});
</script>

<template>
    <InternalDashboardLayout :title="isResultView ? 'Monthly' : 'Monthly HRD'" :user="user" content-width="wide">
        <KpiEmployeeLayout>

            <template v-if="isResultView">
                <KpiEmployeeHeader v-if="isResultView && resultEmployeeId" :employee-id="resultEmployeeId" :period="period" :available-periods="employeePeriods" active-tab="monthly" page-title="Monthly" :status-label="resultStatus" />

                <section class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 flex items-center gap-2 text-lg font-bold text-[#0b347d]"><span class="text-xl">👤</span> Informasi Karyawan</h2>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="field in [
                            ['Nama', employeeHeader?.nama], ['NIK', employeeHeader?.nik || '-'], ['Perusahaan', employeeHeader?.perusahaan || '-'], ['Jabatan', employeeHeader?.jabatan || '-'],
                            ['Departemen', employeeHeader?.departemen || '-'], ['Penempatan', employeeHeader?.penempatan || '-'], ['Atasan Langsung', employeeHeader?.atasan_langsung || '-'], ['Periode Penilaian', periodLabel]
                        ]" :key="field[0]" class="rounded-lg bg-[#f3f7fd] px-3 py-2">
                            <div class="text-[11px] text-[#6480aa]">{{ field[0] }}</div>
                            <div class="text-sm font-bold text-[#173f82]">{{ field[1] || '-' }}</div>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <section class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm">
                        <h2 class="text-lg font-bold text-[#0b347d]">🎯 1. Kinerja Operasional</h2>
                        <p class="mt-1 text-xs text-[#53709d]">Penilaian kinerja operasional berdasarkan pencapaian target dan standar pekerjaan harian.</p>
                        <div class="mt-4 flex items-center justify-between rounded-lg bg-[#eef4fb] p-3">
                            <span class="text-sm text-[#53709d]">Nilai Anda</span>
                            <strong class="text-2xl text-emerald-600">{{ operationalScore }} <small class="text-xs font-normal text-[#53709d]">dari 45</small></strong>
                        </div>
                        <div class="mt-4 grid gap-1" style="grid-template-columns: repeat(45, minmax(0, 1fr))" aria-label="Skala kinerja operasional">
                            <span v-for="n in 45" :key="n" :class="n <= operationalScore ? 'bg-blue-600' : 'bg-slate-200'" class="h-6 rounded-sm"></span>
                        </div>
                        <div class="mt-1 flex justify-between text-[10px] text-[#53709d]"><span>1</span><span>15</span><span>30</span><span>45</span></div>
                    </section>

                    <section class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm">
                        <h2 class="text-lg font-bold text-[#0b347d]">▤ 2. Penilaian Umum</h2>
                        <p class="mt-1 text-xs text-[#53709d]">Penilaian berdasarkan dimensi perilaku dan kompetensi.</p>
                        <div class="mt-3 overflow-hidden rounded-lg border border-blue-100 text-xs">
                            <div v-for="row in [
                                ['Sikap Kerja', resultMonthly.sikap_kerja], ['Team Work', resultMonthly.team_work], ['Inisiatif', resultMonthly.inisiatif], ...(hasLeadershipDimension ? [['Kepemimpinan / Potensi Kepemimpinan', resultMonthly.kepemimpinan]] : [])
                            ]" :key="row[0]" class="grid grid-cols-[1.3fr_1fr_56px] border-b border-blue-50 last:border-0">
                                <span class="px-3 py-2 font-semibold text-[#173f82]">{{ row[0] }}</span><span class="px-3 py-2 text-[#53709d]">Penilaian MPA</span><span class="px-2 py-2 text-center font-bold text-emerald-700">{{ row[1] ?? '-' }}</span>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="flex items-center justify-between rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                    <div><h2 class="text-lg font-bold text-[#0b347d]">▥ 3. Rata-Rata Penilaian Umum</h2><p class="text-xs text-[#53709d]">Rata-rata dari seluruh dimensi penilaian umum.</p></div>
                    <strong class="text-2xl text-emerald-600">{{ normalizedGeneralScore }} <small class="text-xs font-normal text-[#53709d]">dari 5</small></strong>
                </section>

                <section class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-lg font-bold text-[#0b347d]">▤ 4. Data dari HRD</h2>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="overflow-hidden rounded-lg border border-blue-100"><h3 class="bg-[#eef4fb] px-3 py-2 font-bold text-[#173f82]">Penilaian Absensi</h3><table class="w-full text-xs"><thead class="bg-[#f7faff] text-[#53709d]"><tr><th class="px-2 py-2 text-left">Kriteria</th><th>Kode</th><th>Jumlah</th><th>Total</th></tr></thead><tbody><tr v-for="row in adjustmentRows" :key="row.kode" class="border-t border-blue-50"><td class="px-2 py-1.5">{{ row.label }}</td><td class="text-center">{{ row.kode }}</td><td class="text-center">{{ row.jumlah }}</td><td class="text-center">{{ Number(row.total).toFixed(2) }}</td></tr></tbody></table><div class="border-t border-blue-100 px-3 py-2 text-right font-bold text-[#173f82]">Nilai Absensi: {{ Number(resultMonthly.attendance_score || 0).toFixed(2) }}</div></div>
                        <div class="overflow-hidden rounded-lg border border-blue-100"><h3 class="bg-[#eef4fb] px-3 py-2 font-bold text-[#173f82]">Penambahan atau Pengurangan Nilai</h3><table class="w-full text-xs"><thead class="bg-[#f7faff] text-[#53709d]"><tr><th class="px-2 py-2 text-left">Kriteria</th><th>Angka</th><th>Jumlah</th><th>Total</th></tr></thead><tbody><tr v-for="row in rewardRows" :key="row.jenis" class="border-t border-blue-50"><td class="px-2 py-1.5">{{ row.label }}</td><td class="text-center">{{ row.rate }}</td><td class="text-center">{{ row.jumlah }}</td><td class="text-center">{{ row.total }}</td></tr></tbody></table><div class="border-t border-blue-100 px-3 py-2 text-right font-bold text-[#173f82]">Total: {{ Number(resultMonthly.reward_punishment_score || 0).toFixed(2) }}</div></div>
                    </div>
                </section>

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <section class="rounded-xl border border-blue-100 bg-white p-4"><h2 class="text-lg font-bold text-[#0b347d]">▤ 5. Penjelasan Berkaitan Dengan Performance</h2><p class="mt-3 rounded-lg bg-[#f3f7fd] p-3 text-sm text-[#53709d]">{{ resultMonthly.performance || 'Belum tersedia.' }}</p></section>
                    <section class="rounded-xl border border-blue-100 bg-white p-4"><h2 class="text-lg font-bold text-[#0b347d]">💡 6. Rencana Perbaikan (coaching, counseling, dll)</h2><p class="mt-3 rounded-lg bg-[#f3f7fd] p-3 text-sm text-[#53709d]">{{ resultMonthly.coaching || 'Belum tersedia.' }}</p></section>
                </div>

                <section class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm"><h2 class="mb-3 text-lg font-bold text-[#0b347d]">👤 Persetujuan dan Tanda Tangan</h2><div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4"><div v-for="card in signatureCards" :key="card.key" class="rounded-lg border border-blue-100 p-3"><div class="flex items-center justify-between"><strong class="text-sm text-[#173f82]">{{ card.label }}</strong><span :class="signatures?.[card.key] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'" class="rounded px-2 py-1 text-[10px] font-bold">{{ signatures?.[card.key] ? (signatures[card.key].source === 'automatic' ? 'Ditandatangani Otomatis' : 'Sudah Tanda Tangan') : (card.key === 'atasan_kedua' && !participant?.atasan_kedua_id ? 'N/A' : 'Menunggu Tanda Tangan') }}</span></div><div class="mt-3 flex min-h-[74px] items-center gap-3"><img v-if="signatures?.[card.key]?.signature_url" :src="signatures[card.key].signature_url" class="h-14 w-24 object-contain" alt="Tanda tangan"><div v-else class="flex h-14 w-24 items-center justify-center rounded bg-slate-50 text-xs text-slate-400">—</div><div class="text-xs"><div class="font-bold text-[#173f82]">{{ card.person }}</div><div class="text-[#53709d]">{{ signatures?.[card.key]?.signed_at || (signatures?.[card.key]?.reason ? signatures[card.key].reason : '-') }}</div><button v-if="card.canSign" type="button" @click="signMonthly()" class="mt-2 rounded bg-blue-600 px-2 py-1 text-[10px] font-bold text-white">Tanda Tangani Monthly</button></div></div></div></div></section>
            </template>

            <template v-else>
            
            <!-- Header Card -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                        <Link :href="route('dashboard.kpi.index')" class="hover:underline">KPI Utama</Link>
                        <span>/</span>
                        <span>Periode {{ periodLabel }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900">Monthly HRD Completion & Publish</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        Kelola Nilai Absensi & Reward/Punishment · Tanggal Publish Resmi: <strong>Maksimal Tanggal 9</strong>
                    </p>
                </div>

                <!-- Publish Button (Period-Wide) -->
                <div v-if="!isMonitoring" class="flex items-center gap-3">
                    <button 
                        @click="publishModalOpen = true"
                        :disabled="!isPublishable"
                        class="px-5 py-2.5 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition shadow-md shadow-emerald-200 disabled:opacity-50 disabled:bg-slate-300"
                    >
                        Publish Periode Ini
                    </button>
                </div>
            </div>


            <!-- Completeness Warning -->
            <div v-if="!isMonitoring && !isPublishable" class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-xl text-sm flex items-center gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <strong class="font-bold">Periode Belum Dapat Dipublish!</strong>
                    <p class="text-xs mt-0.5">Seluruh peserta KPI pada periode ini wajib menyelesaikan record Monthly/MPA terlebih dahulu sebelum periode dapat dipublish.</p>
                </div>
            </div>

            <!-- Layout: Participants List Table + Component Form -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left Column: Participants Selection -->
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm space-y-4 h-fit">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-sm font-bold text-slate-800">Daftar Peserta Monthly</h2>
                        <p class="text-[11px] text-slate-500">Pilih peserta untuk mengedit komponen absensi dan reward/punishment.</p>
                    </div>

                    <div class="space-y-2 max-h-[600px] overflow-y-auto pr-1">
                        <div 
                            v-for="m in monthlies" 
                            :key="m.id"
                            @click="selectMonthly(m)"
                            :class="[
                                'p-3 rounded-xl border transition cursor-pointer flex items-center justify-between',
                                selectedMonthly?.id === m.id 
                                    ? 'bg-emerald-50 border-emerald-300 ring-2 ring-emerald-400/20' 
                                    : 'bg-slate-50 border-slate-200/80 hover:bg-slate-100'
                            ]"
                        >
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">{{ m.nama }}</h4>
                                <span class="text-[11px] text-slate-500 block">{{ m.jabatan }}</span>
                            </div>

                            <div class="text-right">
                                <span 
                                    :class="[
                                        'px-2 py-0.5 text-[10px] font-bold rounded uppercase block mb-1',
                                        m.status === 'published' ? 'bg-emerald-500 text-white' :
                                        m.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                                        m.status === 'HRD_INCOMPLETE' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-600'
                                    ]"
                                >
                                    {{ m.status }}
                                </span>
                                <span class="text-[11px] text-slate-500">MPA: {{ m.mpa_score ? m.mpa_score.toFixed(2) : '0.00' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: HRD Components Form -->
                <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-6" v-if="selectedMonthly">
                    
                    <div class="border-b border-slate-100 pb-4">
                        <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Komponen HRD Monthly</span>
                        <h2 class="text-xl font-bold text-slate-900 mt-0.5">{{ selectedMonthly.nama }}</h2>
                        <p class="text-xs text-slate-500">{{ selectedMonthly.jabatan }} · {{ selectedMonthly.departemen }}</p>
                    </div>

                    <form @submit.prevent="saveMonthlyComponents" class="space-y-6">
                        
                        <!-- Attendance Adjustments Section -->
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">1. Penilaian Absensi & Potongan Jam</h3>
                                    <p class="text-xs text-slate-500">Formula Absensi = (10 - Total Potongan) × 0.5 (Tanpa Clamping)</p>
                                </div>
                                <span class="text-sm font-black text-emerald-700 bg-emerald-100 px-3 py-1 rounded-xl">
                                    Skor: {{ calculatedAttendanceScore }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 pt-2">
                                <div v-for="(adj, idx) in form.adjustments" :key="idx" class="bg-white p-3 rounded-xl border border-slate-200">
                                    <label class="block text-[11px] font-bold text-slate-700 mb-1">{{ adj.label }}</label>
                                    <input 
                                        type="number" 
                                        v-model.number="adj.jumlah" 
                                        min="0"
                                        :disabled="isMonitoring"
                                        placeholder="Jumlah kejadian"
                                        class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Reward & Punishment Section -->
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">2. Reward & Punishment (Optional)</h3>
                                    <p class="text-xs text-slate-500">Major/Minor Award (+) & Demerit (-) (Tanpa Clamping)</p>
                                </div>
                                <span class="text-sm font-black text-emerald-700 bg-emerald-100 px-3 py-1 rounded-xl">
                                    Skor: {{ calculatedRewardScore }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                <div v-for="(rew, idx) in form.rewards" :key="idx" class="bg-white p-3 rounded-xl border border-slate-200">
                                    <label class="block text-[11px] font-bold text-slate-700 mb-1">{{ rew.label }}</label>
                                    <input 
                                        type="number" 
                                        v-model.number="rew.jumlah" 
                                        min="0"
                                        :disabled="isMonitoring"
                                        placeholder="Jumlah kejadian"
                                        class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Save Component Action -->
                        <div v-if="!isMonitoring" class="pt-4 border-t border-slate-100 flex justify-end">
                            <button 
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-sm rounded-xl hover:bg-emerald-700 transition shadow-md shadow-emerald-200 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Komponen Monthly' }}
                            </button>
                        </div>

                    </form>

                </div>

            </div>

            <!-- Publish Confirmation Modal -->
            <div v-if="publishModalOpen" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl text-center">
                    <div class="mx-auto w-12 h-12 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xl font-bold">
                        🚀
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Publish Monthly Periode {{ periodLabel }}?</h3>
                    <p class="text-xs text-slate-500">
                        Seluruh record Monthly peserta akan dipublish sekaligus dan tanda tangan otomatis HRD akan dicatat.
                    </p>

                    <div class="flex items-center justify-center gap-3 pt-2">
                        <button @click="publishModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Batal</button>
                        <button @click="executePublish" class="px-5 py-2 text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 rounded-xl shadow-md shadow-emerald-200">Ya, Publish Sekarang</button>
                    </div>
                </div>
            </div>
            </template>

        </KpiEmployeeLayout>
    </InternalDashboardLayout>
</template>
