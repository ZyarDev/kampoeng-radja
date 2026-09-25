<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    employeeId: { type: Number, required: true },
    period: { type: Object, default: () => ({}) },
    availablePeriods: { type: Array, default: () => [] },
    activeTab: { type: String, required: true },
    pageTitle: { type: String, required: true },
    statusLabel: { type: String, default: '' },
    statusClass: { type: String, default: 'bg-blue-50 text-blue-700' },
});

const tabs = [
    { key: 'daily', label: 'Daily Report', icon: '▣' },
    { key: 'individual', label: 'Kinerja Individu', icon: '▥' },
    { key: 'ops', label: 'Kinerja OPS', icon: '▤' },
    { key: 'monthly', label: 'Monthly', icon: '▦' },
    { key: 'final', label: 'Nilai Akhir', icon: '★' },
];
const periodId = computed(() => Number(props.period?.id || 0));
const currentIndex = computed(() => props.availablePeriods.findIndex((item) => Number(item.id) === periodId.value));
const selectedPeriod = computed(() => props.availablePeriods[currentIndex.value] || props.period);
const previousPeriod = computed(() => currentIndex.value > 0 ? props.availablePeriods[currentIndex.value - 1] : null);
const nextPeriod = computed(() => currentIndex.value >= 0 && currentIndex.value < props.availablePeriods.length - 1 ? props.availablePeriods[currentIndex.value + 1] : null);
const destination = (key, id = periodId.value) => key === 'daily'
    ? route('dashboard.kpi.daily', { period_id: id, karyawan_id: props.employeeId })
    : route(`dashboard.kpi.${key}`, { period: id, karyawan_id: props.employeeId });
const go = (id) => { if (id) router.get(destination(props.activeTab, id)); };
</script>

<template>
    <section class="rounded-2xl border border-[#dce5f1] bg-white p-5 shadow-[0_8px_30px_rgba(30,64,175,0.06)] sm:p-6">
        <div class="flex min-h-[68px] flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#5273a8]">KPI Karyawan</p>
                <h1 class="mt-1 text-2xl font-bold text-[#0b3475]">{{ pageTitle }}</h1>
            </div>
            <span v-if="statusLabel" class="inline-flex w-fit items-center rounded-lg px-3 py-2 text-xs font-bold" :class="statusClass">● {{ statusLabel }}</span>
        </div>
        <div class="mt-5 flex flex-col gap-3 rounded-xl border border-[#dce5f1] bg-[#f8fbff] p-3 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-[11px] font-bold uppercase tracking-[0.12em] text-[#6079a4]">Periode KPI</p><p class="mt-1 text-xs font-semibold" :class="selectedPeriod?.is_active ? 'text-emerald-700' : 'text-slate-500'">{{ selectedPeriod?.is_active ? 'Periode Aktif' : 'Periode Historis' }}</p></div>
            <div class="flex items-center gap-2">
                <button type="button" aria-label="Periode sebelumnya" :disabled="!previousPeriod" class="grid h-9 w-9 place-items-center rounded-lg border border-[#cbd8ea] text-lg text-[#173467] disabled:cursor-not-allowed disabled:opacity-35" @click="go(previousPeriod?.id)">‹</button>
                <select :value="periodId" class="h-9 min-w-[180px] rounded-lg border-[#cbd8ea] bg-white text-sm font-semibold text-[#173467]" aria-label="Pilih periode KPI" @change="go(Number($event.target.value))">
                    <option v-for="item in [...availablePeriods].reverse()" :key="item.id" :value="item.id">{{ item.label }}</option>
                </select>
                <button type="button" aria-label="Periode berikutnya" :disabled="!nextPeriod" class="grid h-9 w-9 place-items-center rounded-lg border border-[#cbd8ea] text-lg text-[#173467] disabled:cursor-not-allowed disabled:opacity-35" @click="go(nextPeriod?.id)">›</button>
            </div>
        </div>
        <nav class="mt-4 grid overflow-hidden rounded-lg bg-[#eef4fb] sm:grid-cols-5">
            <Link v-for="tab in tabs" :key="tab.key" :href="destination(tab.key)" :class="tab.key === activeTab ? 'bg-[#1263e8] text-white shadow-sm' : 'text-[#536b95] hover:bg-blue-50'" class="flex min-h-[48px] items-center justify-center gap-2 border-white px-3 text-xs font-semibold sm:border-r sm:text-sm"><span class="text-base">{{ tab.icon }}</span>{{ tab.label }}</Link>
        </nav>
    </section>
</template>
