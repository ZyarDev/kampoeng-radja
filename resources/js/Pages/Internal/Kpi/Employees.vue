<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';

const props = defineProps({
    user: Object,
    period: Object,
    participants: Array,
});

const searchQuery = ref('');
const selectedDepartemen = ref('');

const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const periodLabel = computed(() => {
    return `${monthNames[props.period.bulan - 1]} ${props.period.tahun}`;
});

const departemenList = computed(() => {
    const list = new Set();
    props.participants?.forEach(p => {
        if (p.departemen) list.add(p.departemen);
    });
    return Array.from(list);
});

const filteredParticipants = computed(() => {
    return props.participants?.filter(p => {
        const matchesSearch = !searchQuery.value || 
            p.nama.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            p.nip.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            p.jabatan.toLowerCase().includes(searchQuery.value.toLowerCase());
        
        const matchesDep = !selectedDepartemen.value || p.departemen === selectedDepartemen.value;

        return matchesSearch && matchesDep;
    }) || [];
});
</script>

<template>
    <InternalDashboardLayout title="KPI-Karyawan" :user="user" content-width="wide">
        <div class="mx-auto max-w-[1280px] p-6 space-y-6">
            
            <!-- Breadcrumb & Header Card -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 mb-1">
                        <Link :href="route('dashboard.kpi.index')" class="hover:underline">KPI Utama</Link>
                        <span>/</span>
                        <span>Daftar Peserta KPI</span>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900">KPI-Karyawan · Periode {{ periodLabel }}</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Monitoring hirarki KPI bawahan dan status pengisian nilai per periode.</p>
                </div>

                <div class="flex items-center gap-3">
                    <span class="px-4 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-xl">
                        Total Peserta: {{ participants?.length || 0 }} Orang
                    </span>
                </div>
            </div>

            <!-- Filters & Search Bar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <!-- Search Input -->
                    <input 
                        type="text" 
                        v-model="searchQuery" 
                        placeholder="Cari nama, NIP, atau jabatan..." 
                        class="w-full sm:w-72 text-xs bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-emerald-500"
                    />

                    <!-- Filter Departemen -->
                    <select 
                        v-model="selectedDepartemen"
                        class="text-xs bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-emerald-500 font-medium"
                    >
                        <option value="">Semua Departemen</option>
                        <option v-for="dep in departemenList" :key="dep" :value="dep">{{ dep }}</option>
                    </select>
                </div>
            </div>

            <!-- Table Matching Hierarchy Monitoring PRD -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 font-bold uppercase tracking-wider">
                                <th class="p-4 w-12 text-center">No</th>
                                <th class="p-4">Nama Karyawan / NIP</th>
                                <th class="p-4">Jabatan</th>
                                <th class="p-4">Departemen</th>
                                <th class="p-4">Atasan Langsung</th>
                                <th class="p-4 text-center">Status KI</th>
                                <th class="p-4 text-center">Skor KI (Max 80)</th>
                                <th class="p-4 text-center">Item OPS</th>
                                <th class="p-4 text-center">Missing Daily / SP1</th>
                                <th class="p-4 text-center">Menu Akses</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredParticipants.length === 0">
                                <td colspan="10" class="p-8 text-center text-slate-400 text-sm">
                                    Tidak ada data karyawan KPI yang sesuai dengan kriteria pencarian.
                                </td>
                            </tr>

                            <tr 
                                v-for="(p, index) in filteredParticipants" 
                                :key="p.id"
                                class="hover:bg-slate-50/80 transition"
                            >
                                <td class="p-4 text-center font-bold text-slate-400">{{ index + 1 }}</td>
                                
                                <td class="p-4">
                                    <strong class="text-sm font-bold text-slate-900 block">{{ p.nama }}</strong>
                                    <span class="text-slate-400 text-[11px]">NIP: {{ p.nip }}</span>
                                </td>

                                <td class="p-4 font-medium text-slate-700">{{ p.jabatan }}</td>
                                
                                <td class="p-4 font-medium text-slate-600">{{ p.departemen || '-' }}</td>

                                <td class="p-4 text-slate-600 font-medium">{{ p.atasan_langsung || '-' }}</td>

                                <td class="p-4 text-center">
                                    <span 
                                        :class="[
                                            'px-2.5 py-1 text-[11px] font-bold rounded-lg uppercase',
                                            p.ki_status === 'approved' ? 'bg-emerald-100 text-emerald-800' :
                                            p.ki_status === 'submitted' ? 'bg-amber-100 text-amber-800' :
                                            p.ki_status === 'not_filled' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600'
                                        ]"
                                    >
                                        {{ p.ki_status === 'approved' ? 'Disetujui' : p.ki_status === 'submitted' ? 'Diisi' : p.ki_status === 'not_filled' ? 'Auto 0' : 'Draft' }}
                                    </span>
                                </td>

                                <td class="p-4 text-center font-bold text-emerald-700 text-sm">
                                    {{ p.ki_score ? p.ki_score.toFixed(2) : '0.00' }}
                                </td>

                                <td class="p-4 text-center font-bold text-slate-700">
                                    {{ p.ops_count }} Item
                                </td>

                                <td class="p-4 text-center">
                                    <span :class="['px-2 py-0.5 text-xs font-bold rounded', p.missing_daily_count > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700']">
                                        {{ p.missing_daily_count }} Hari
                                    </span>
                                    <span v-if="p.needs_sp1_followup" class="mt-1 block text-[10px] font-extrabold text-rose-600 bg-rose-50 px-1 py-0.5 rounded border border-rose-200">
                                        ⚠️ Perlu SP1
                                    </span>
                                </td>

                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <Link 
                                            v-if="p.can_edit_ki"
                                            :href="route('dashboard.kpi.individual', { period: period.id, karyawan_id: p.karyawan_id })"
                                            class="px-2.5 py-1 text-[11px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg transition border border-emerald-200"
                                        >
                                            Form KI
                                        </Link>
                                        <Link 
                                            v-if="p.can_edit_ops"
                                            :href="route('dashboard.kpi.ops', { period: period.id, karyawan_id: p.karyawan_id })"
                                            class="px-2.5 py-1 text-[11px] font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg transition border border-blue-200"
                                        >
                                            Form OPS
                                        </Link>
                                        <span v-if="!p.can_edit_ki && !p.can_edit_ops" class="text-slate-400 text-[11px]">
                                            Monitoring Only
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </InternalDashboardLayout>
</template>
