<script setup>
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    user: Object,
    period: Object,
    participant: Object,
    score: Object,
    isWindowAllowed: Boolean,
});

const form = useForm({
    capaian_departemen: props.score?.capaian_departemen ?? 0,
    perawatan_aset: props.score?.perawatan_aset ?? 0,
    kebersihan_kerapihan: props.score?.kebersihan_kerapihan ?? 0,
    keterangan_capaian: props.score?.keterangan_capaian ?? '',
    keterangan_aset: props.score?.keterangan_aset ?? '',
    keterangan_kebersihan: props.score?.keterangan_kebersihan ?? '',
});

// PRD FINAL Formula: score = (capaian * 0.70) + (aset * 0.05) + (kebersihan * 0.05)
// Normal Maximum = 80.
const calculatedKiScore = computed(() => {
    const c = Number(form.capaian_departemen) || 0;
    const a = Number(form.perawatan_aset) || 0;
    const k = Number(form.kebersihan_kerapihan) || 0;
    return ((c * 0.70) + (a * 0.05) + (k * 0.05)).toFixed(2);
});

const isApproved = computed(() => props.score?.status === 'approved');
const isSubmitted = computed(() => props.score?.status === 'submitted');
const isNotFilled = computed(() => props.score?.status === 'not_filled');

const submitScore = () => {
    form.post(route('dashboard.kpi.individual', props.period.id), {
        preserveScroll: true,
    });
};

const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const periodLabel = computed(() => {
    return `${monthNames[props.period.bulan - 1]} ${props.period.tahun}`;
});
</script>

<template>
    <InternalDashboardLayout title="Kinerja Individu" :user="user">
        <div class="mx-auto max-w-5xl p-6 space-y-6">
            
            <!-- Back & Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                        <Link :href="route('dashboard.kpi.index')" class="hover:underline">KPI Utama</Link>
                        <span>/</span>
                        <span>Periode {{ periodLabel }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900">Kinerja Individu (KI)</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        Normal Maksimum Skor KI: <strong class="text-emerald-700">80.00</strong> · Peserta: <strong>{{ participant.karyawan_snapshot || user.name }}</strong> ({{ participant.jabatan_snapshot }})
                    </p>
                </div>

                <!-- Status Badge -->
                <div class="flex items-center gap-2">
                    <span 
                        :class="[
                            'px-4 py-1.5 text-xs font-bold rounded-xl uppercase tracking-wider',
                            isApproved ? 'bg-emerald-500 text-white shadow-sm' :
                            isSubmitted ? 'bg-amber-500 text-white shadow-sm' :
                            isNotFilled ? 'bg-rose-500 text-white shadow-sm' : 'bg-slate-200 text-slate-700'
                        ]"
                    >
                        {{ isApproved ? 'Disetujui' : isSubmitted ? 'Telah Diisi' : isNotFilled ? 'Tidak Mengisi (Auto 0)' : 'Draft / Belum Diisi' }}
                    </span>
                </div>
            </div>

            <!-- Hard Window Warning (Days 1-2 only) -->
            <div v-if="!isWindowAllowed" class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-xl text-sm flex items-center gap-3">
                <span class="text-xl">🔒</span>
                <div>
                    <strong class="font-bold">Batas Pengisian Terkunci!</strong>
                    <p class="text-xs mt-0.5">Sesuai PRD, pengisian Kinerja Individu hanya dibuka pada <strong>tanggal 1–2</strong> pada bulan setelah bulan performa.</p>
                </div>
            </div>

            <!-- Main Form & Evaluation Table -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-6">
                
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-bold text-slate-800">Formulir Penilaian Kinerja Individu</h2>
                    <p class="text-xs text-slate-500">Nilai masing-masing komponen evaluasi diri dari skala 1 sampai 100.</p>
                </div>

                <div class="space-y-6">
                    
                    <!-- Component 1: Capaian Departemen (70%) -->
                    <div class="p-5 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-3">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">1. Capaian Departemen</h3>
                                <p class="text-xs text-slate-500">Pencapaian target kerja dan tugas utama divisi/departemen.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-slate-500">Bobot Komponen:</span>
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-lg">70% (0.70)</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nilai (1 - 100) <span class="text-rose-500">*</span></label>
                                <input 
                                    type="number" 
                                    v-model.number="form.capaian_departemen" 
                                    min="0" 
                                    max="100"
                                    :disabled="!isWindowAllowed || isApproved"
                                    class="w-full text-sm font-bold bg-white border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Evaluasi Diri / Keterangan Capaian</label>
                                <input 
                                    type="text" 
                                    v-model="form.keterangan_capaian"
                                    :disabled="!isWindowAllowed || isApproved"
                                    placeholder="Catatan tambahan hasil kinerja departemen..."
                                    class="w-full text-sm bg-white border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Component 2: Perawatan Aset (5%) -->
                    <div class="p-5 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-3">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">2. Perawatan Aset Kerja Sesuai Bidang</h3>
                                <p class="text-xs text-slate-500">Tanggung jawab terhadap perawatan alat, fasilitas, dan barang operasional.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-slate-500">Bobot Komponen:</span>
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-lg">5% (0.05)</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nilai (1 - 100) <span class="text-rose-500">*</span></label>
                                <input 
                                    type="number" 
                                    v-model.number="form.perawatan_aset" 
                                    min="0" 
                                    max="100"
                                    :disabled="!isWindowAllowed || isApproved"
                                    class="w-full text-sm font-bold bg-white border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Perawatan Aset</label>
                                <input 
                                    type="text" 
                                    v-model="form.keterangan_aset"
                                    :disabled="!isWindowAllowed || isApproved"
                                    placeholder="Catatan kondisi perawatan barang/aset..."
                                    class="w-full text-sm bg-white border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Component 3: Kebersihan & Kerapihan (5%) -->
                    <div class="p-5 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-3">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">3. Kebersihan & Kerapihan Lingkungan Kerja</h3>
                                <p class="text-xs text-slate-500">Kebersihan area kerja, kerapihan dokumen, dan kerapihan lingkungan kerja.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-slate-500">Bobot Komponen:</span>
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-lg">5% (0.05)</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nilai (1 - 100) <span class="text-rose-500">*</span></label>
                                <input 
                                    type="number" 
                                    v-model.number="form.kebersihan_kerapihan" 
                                    min="0" 
                                    max="100"
                                    :disabled="!isWindowAllowed || isApproved"
                                    class="w-full text-sm font-bold bg-white border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Kebersihan</label>
                                <input 
                                    type="text" 
                                    v-model="form.keterangan_kebersihan"
                                    :disabled="!isWindowAllowed || isApproved"
                                    placeholder="Catatan standar kebersihan lingkungan kerja..."
                                    class="w-full text-sm bg-white border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                />
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Live Score Summary Box (PRD Final Formula) -->
                <div class="mt-6 p-6 bg-slate-900 text-white rounded-2xl flex flex-col md:flex-row items-center justify-between gap-6 shadow-xl">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-widest">Kalkulasi Formula PRD Final</span>
                        <h4 class="text-xl font-bold text-white mt-1">Skor Kinerja Individu (Nilai KI)</h4>
                        <p class="text-xs text-slate-400 mt-1">Formula = (Capaian × 0.70) + (Aset × 0.05) + (Kebersihan × 0.05)</p>
                    </div>

                    <div class="text-center px-4">
                        <span class="block text-xs text-slate-400 mb-1">Nilai KI (Normal Max 80.00)</span>
                        <span class="text-4xl font-black text-emerald-400">{{ calculatedKiScore }}</span>
                    </div>
                </div>

                <!-- Submit Action -->
                <div v-if="isWindowAllowed && !isApproved" class="pt-4 border-t border-slate-100 flex justify-end">
                    <button 
                        type="button"
                        @click="submitScore"
                        :disabled="form.processing"
                        class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-sm rounded-xl hover:bg-emerald-700 transition shadow-md shadow-emerald-200 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Kinerja Individu' }}
                    </button>
                </div>

            </div>

        </div>
    </InternalDashboardLayout>
</template>
