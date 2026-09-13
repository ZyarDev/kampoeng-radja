<script setup>
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    user: Object,
    period: Object,
    monthlies: Array,
    isPublishable: Boolean,
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
</script>

<template>
    <InternalDashboardLayout title="Monthly HRD" :user="user" content-width="wide">
        <div class="mx-auto max-w-[1280px] p-6 space-y-6">
            
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
                <div class="flex items-center gap-3">
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
            <div v-if="!isPublishable" class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-xl text-sm flex items-center gap-3">
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
                                        placeholder="Jumlah kejadian"
                                        class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Save Component Action -->
                        <div class="pt-4 border-t border-slate-100 flex justify-end">
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

        </div>
    </InternalDashboardLayout>
</template>
