<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import InternalDashboardLayout from '@/Layouts/InternalDashboardLayout.vue';
import KpiEmployeeHeader from '@/Components/Internal/KpiEmployeeHeader.vue';
import KpiEmployeeLayout from '@/Components/Internal/KpiEmployeeLayout.vue';

const props = defineProps({
    user: Object,
    period: Object,
    periods: { type: Array, default: () => [] },
    employeePeriods: { type: Array, default: () => [] },
    employeeId: { type: [Number, String], default: null },
    activeTab: { type: String, default: 'individual' },
    moduleName: { type: String, required: true },
    message: { type: String, required: true },
});

const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const periodLabel = computed(() => `${months[(props.period?.bulan || 1) - 1]} ${props.period?.tahun || ''}`);
const options = computed(() => props.employeePeriods.length ? props.employeePeriods : props.periods);
const destination = (id) => {
    if (props.activeTab === 'daily') return route('dashboard.kpi.daily', { period_id: id, karyawan_id: props.employeeId });
    return route(`dashboard.kpi.${props.activeTab}`, { period: id, ...(props.employeeId ? { karyawan_id: props.employeeId } : {}) });
};
const changePeriod = (event) => router.get(destination(Number(event.target.value)), {}, { preserveState: false, replace: true });
</script>

<template>
    <InternalDashboardLayout :title="moduleName" :user="user" content-width="wide">
        <KpiEmployeeLayout>
            <KpiEmployeeHeader
                v-if="employeeId"
                :employee-id="Number(employeeId)"
                :period="period"
                :available-periods="options"
                :active-tab="activeTab"
                :page-title="moduleName"
                status-label="Periode Persiapan"
                status-class="bg-amber-100 text-amber-800"
            />
            <section v-else class="rounded-2xl border border-[#dce5f1] bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#5273a8]">KPI</p>
                        <h1 class="mt-1 text-2xl font-bold text-[#0b3475]">{{ moduleName }}</h1>
                        <p class="mt-1 text-sm font-semibold text-amber-700">Periode Persiapan</p>
                    </div>
                    <label class="text-xs font-semibold text-[#53709d] md:w-64">Periode yang dipantau
                        <select class="mt-1 h-10 w-full rounded-lg border-[#cbd8ea] bg-white px-3 text-sm font-semibold text-[#173467]" :value="period?.id" @change="changePeriod">
                            <option v-for="item in options" :key="item.id" :value="item.id">{{ item.label }}</option>
                        </select>
                    </label>
                </div>
            </section>

            <section class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-8 text-center shadow-sm">
                <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-amber-100 text-2xl text-amber-700">🔒</div>
                <h2 class="mt-4 text-xl font-bold text-amber-900">Periode {{ periodLabel }} masih dalam Persiapan</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm text-amber-800">{{ message }}</p>
                <p class="mt-3 text-xs text-amber-700">Periode tetap dipertahankan sebagai konteks yang sedang dipantau. Aktivitas akan terbuka setelah lifecycle periode aktif.</p>
            </section>
        </KpiEmployeeLayout>
    </InternalDashboardLayout>
</template>
