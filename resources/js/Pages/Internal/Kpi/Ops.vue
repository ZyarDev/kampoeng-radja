<script setup>
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    user: Object,
    period: Object,
    participant: Object,
    items: Array,
    totalKops: Number,
    totalBebanTarget: Number,
    isWindowAllowed: Boolean,
});

const form = useForm({
    items: props.items?.length
        ? props.items.map(i => ({
            id: i.id,
            kpi_item: i.kpi_item,
            maintenance: i.maintenance || '',
            target_unit: i.target_unit,
            tanda: i.tanda || '+',
            frekuensi: i.frekuensi || '',
            hasil: i.hasil !== null && i.hasil !== undefined ? i.hasil : '',
            aktivitas: i.aktivitas || '',
            bukti_evidence: null,
            existing_bukti: i.bukti_path || null,
            preview_url: i.bukti_path || null,
        }))
        : [
            {
                id: null,
                kpi_item: 'Pencapaian Target Operasional 1',
                maintenance: '',
                target_unit: 100,
                tanda: '+',
                frekuensi: 'Bulanan',
                hasil: '',
                aktivitas: '',
                bukti_evidence: null,
                existing_bukti: null,
                preview_url: null,
            }
        ],
});

// File change handler for K-OPS evidence
const handleFileChange = (index, event) => {
    const file = event.target.files[0];
    if (file) {
        form.items[index].bukti_evidence = file;
        form.items[index].preview_url = URL.createObjectURL(file);
    }
};

// Modal image preview state
const modalImage = ref(null);
const openModal = (url) => { modalImage.value = url; };
const closeModal = () => { modalImage.value = null; };

// Calculate total target unit across all items
const calculatedTotalTargetUnit = computed(() => {
    return form.items.reduce((sum, item) => sum + (Number(item.target_unit) || 0), 0);
});

// Rumus Beban Target Item = (Target Unit Item / Total Target Unit) * 10
// Represented as percentage points (e.g. 2.00, 6.25)
const calculateItemBebanTarget = (item) => {
    const total = calculatedTotalTargetUnit.value;
    const target = Number(item.target_unit) || 0;
    if (total <= 0) return '0.00';
    return ((target / total) * 10).toFixed(2);
};

// Rumus Nilai Item = (Hasil / Target Unit) * Beban Target
// NO clamping! If Hasil > Target, Nilai Item > Beban Target.
const calculateItemNilai = (item) => {
    const target = Number(item.target_unit) || 0;
    const hasil = item.hasil !== '' && item.hasil !== null ? Number(item.hasil) : null;
    const beban = Number(calculateItemBebanTarget(item));

    if (hasil === null || target <= 0) return '0.00';

    return ((hasil / target) * beban).toFixed(2);
};

// Total KOPS = SUM(Nilai Item)
const calculatedTotalKops = computed(() => {
    return form.items.reduce((sum, item) => sum + Number(calculateItemNilai(item)), 0).toFixed(2);
});

const isSuperAdmin = computed(() => props.user?.roleName === 'super_admin');

const addItem = () => {
    form.items.push({
        id: null,
        kpi_item: '',
        maintenance: '',
        target_unit: 10,
        tanda: '+',
        frekuensi: 'Bulanan',
        hasil: '',
        aktivitas: '',
        bukti_evidence: null,
        existing_bukti: null,
    });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const saveOps = () => {
    form.post(route('dashboard.kpi.ops', props.period.id), {
        preserveScroll: true,
        forceFormData: true,
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
    <InternalDashboardLayout title="Kinerja OPS" :user="user">
        <div class="mx-auto max-w-6xl p-6 space-y-6">
            
            <!-- Header Card -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                        <Link :href="route('dashboard.kpi.index')" class="hover:underline">KPI Utama</Link>
                        <span>/</span>
                        <span>Periode {{ periodLabel }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900">Kinerja Operasional / OPS (KOPS)</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        Target Normal KOPS: <strong class="text-emerald-700">10.00</strong> (Tanpa Batas Maksimal) · Peserta: <strong>{{ participant.karyawan_snapshot || user.name }}</strong> ({{ participant.jabatan_snapshot }})
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <span 
                        :class="[
                            'px-4 py-1.5 text-xs font-bold rounded-xl uppercase tracking-wider',
                            items && items.length > 0 ? 'bg-emerald-500 text-white shadow-sm' : 'bg-slate-200 text-slate-700'
                        ]"
                    >
                        {{ items && items.length > 0 ? 'Tersimpan' : 'Draft' }}
                    </span>
                </div>
            </div>

            <!-- Window Lock Warning -->
            <div v-if="!isWindowAllowed" class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-xl text-sm flex items-center gap-3">
                <span class="text-xl">🔒</span>
                <div>
                    <strong class="font-bold">Batas Pengisian Terkunci!</strong>
                    <p class="text-xs mt-0.5">Sesuai PRD, pengisian Kinerja OPS hanya dibuka pada <strong>tanggal 1–2</strong> pada bulan setelah bulan performa.</p>
                </div>
            </div>

            <!-- Main Items Table / Form -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-6">
                
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Daftar Item Kinerja Operasional</h2>
                        <p class="text-xs text-slate-500">Beban Target otomatis dihitung dari proporsi Target Unit (Total = 10.00). Nilai Item = (Hasil / Target) × Beban Target.</p>
                    </div>
                    
                    <button 
                        v-if="isWindowAllowed || isSuperAdmin"
                        type="button"
                        @click="addItem"
                        class="px-3 py-1.5 text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg transition border border-emerald-200"
                    >
                        + Tambah Item OPS
                    </button>
                </div>

                <!-- Form Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 font-bold uppercase tracking-wider">
                                <th class="p-3 w-10 text-center">No</th>
                                <th class="p-3">Item KPI Operasional</th>
                                <th class="p-3 w-28">Maintenance</th>
                                <th class="p-3 w-24 text-right">Target Unit</th>
                                <th class="p-3 w-16 text-center">Tanda</th>
                                <th class="p-3 w-24 text-right">Beban Target</th>
                                <th class="p-3 w-24 text-right">Hasil (Real)</th>
                                <th class="p-3 w-28 text-right">Nilai Item</th>
                                <th class="p-3 w-32">Bukti Foto</th>
                                <th class="p-3 w-10 text-center" v-if="isWindowAllowed || isSuperAdmin">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(item, idx) in form.items" :key="idx" class="hover:bg-slate-50/80 transition">
                                <td class="p-3 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                                
                                <!-- Item Name -->
                                <td class="p-3">
                                    <input 
                                        type="text" 
                                        v-model="item.kpi_item"
                                        :disabled="!isSuperAdmin && !isWindowAllowed"
                                        placeholder="Nama item kegiatan operasional..."
                                        class="w-full text-xs bg-white border border-slate-200 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                    />
                                </td>

                                <!-- Maintenance -->
                                <td class="p-3">
                                    <input 
                                        type="text" 
                                        v-model="item.maintenance"
                                        :disabled="!isSuperAdmin && !isWindowAllowed"
                                        placeholder="Maintenance..."
                                        class="w-full text-xs bg-white border border-slate-200 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                    />
                                </td>

                                <!-- Target Unit -->
                                <td class="p-3">
                                    <input 
                                        type="number" 
                                        v-model.number="item.target_unit"
                                        step="any"
                                        min="0.0001"
                                        :disabled="!isSuperAdmin && !isWindowAllowed"
                                        class="w-full text-xs text-right font-semibold bg-white border border-slate-200 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                    />
                                </td>

                                <!-- Tanda (+/-) -->
                                <td class="p-3">
                                    <select 
                                        v-model="item.tanda"
                                        :disabled="!isSuperAdmin && !isWindowAllowed"
                                        class="w-full text-xs text-center font-bold bg-white border border-slate-200 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                    >
                                        <option value="+">+</option>
                                        <option value="-">-</option>
                                    </select>
                                </td>

                                <!-- Beban Target (Calculated automatically per PRD) -->
                                <td class="p-3 text-right">
                                    <span class="font-bold text-slate-800 bg-slate-100 px-2 py-1 rounded block">
                                        {{ calculateItemBebanTarget(item) }}
                                    </span>
                                </td>

                                <!-- Hasil (Realisasi oleh Employee) -->
                                <td class="p-3">
                                    <input 
                                        type="number" 
                                        v-model="item.hasil"
                                        step="any"
                                        min="0"
                                        :disabled="!isWindowAllowed && !isSuperAdmin"
                                        placeholder="0"
                                        class="w-full text-xs text-right font-bold text-emerald-700 bg-white border border-slate-200 rounded-lg p-2 focus:ring-2 focus:ring-emerald-500 disabled:bg-slate-100"
                                    />
                                </td>

                                <!-- Nilai Item (Formula PRD Final) -->
                                <td class="p-3 text-right">
                                    <span class="font-black text-emerald-600 text-sm">
                                        {{ calculateItemNilai(item) }}
                                    </span>
                                </td>

                                <!-- Evidence Photo Upload & Preview -->
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        <input 
                                            v-if="isWindowAllowed || isSuperAdmin"
                                            type="file" 
                                            accept="image/*"
                                            @change="e => handleFileChange(idx, e)"
                                            class="text-[10px] text-slate-500 w-28 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:font-semibold file:bg-emerald-50 file:text-emerald-700"
                                        />
                                        <img 
                                            v-if="item.preview_url" 
                                            :src="item.preview_url" 
                                            alt="Bukti Evidence KOPS" 
                                            @click="openModal(item.preview_url)"
                                            class="h-8 w-8 object-cover rounded border border-slate-300 cursor-pointer hover:opacity-80 transition"
                                        />
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="p-3 text-center" v-if="isWindowAllowed || isSuperAdmin">
                                    <button 
                                        v-if="form.items.length > 1"
                                        type="button" 
                                        @click="removeItem(idx)"
                                        class="text-rose-500 hover:text-rose-700 font-bold text-sm"
                                    >
                                        ✕
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- KOPS Score Summary Footer (PRD Final) -->
                <div class="p-6 bg-slate-900 text-white rounded-2xl flex flex-col md:flex-row items-center justify-between gap-6 shadow-xl">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-widest">Formula KOPS PRD Final</span>
                        <h4 class="text-xl font-bold text-white mt-1">Total Skor Kinerja Operasional (KOPS)</h4>
                        <p class="text-xs text-slate-400 mt-1">Target Normal = 10.00. Nilai KOPS boleh > 10.00 apabila Hasil melampaui Target (Tanpa Clamping).</p>
                    </div>

                    <div class="flex items-center gap-6 divide-x divide-slate-700">
                        <div class="text-center px-4">
                            <span class="block text-xs text-slate-400">Total Beban Target</span>
                            <span class="text-2xl font-black text-amber-400">
                                10.00
                            </span>
                        </div>
                        <div class="text-center pl-6">
                            <span class="block text-xs text-slate-400">Skor KOPS Final</span>
                            <span class="text-3xl font-black text-emerald-400">{{ calculatedTotalKops }}</span>
                        </div>
                    </div>
                </div>

                <!-- Submit Action -->
                <div v-if="isWindowAllowed || isSuperAdmin" class="pt-4 border-t border-slate-100 flex justify-end">
                    <button 
                        type="button"
                        @click="saveOps"
                        :disabled="form.processing"
                        class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-sm rounded-xl hover:bg-emerald-700 transition shadow-md shadow-emerald-200 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Kinerja OPS' }}
                    </button>
                </div>

            </div>

            <!-- Image Modal Preview -->
            <div v-if="modalImage" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4" @click.self="closeModal">
                <div class="relative max-w-3xl bg-white p-2 rounded-2xl overflow-hidden shadow-2xl">
                    <button @click="closeModal" class="absolute top-4 right-4 bg-slate-900/80 text-white rounded-full p-2 text-xs font-bold hover:bg-slate-900">
                        ✕ Tutup
                    </button>
                    <img :src="modalImage" class="max-h-[80vh] w-auto rounded-xl" />
                </div>
            </div>

        </div>
    </InternalDashboardLayout>
</template>
