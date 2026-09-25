<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    periodId: { type: Number, required: true },
    employeeId: { type: Number, required: true },
    active: { type: String, required: true },
    periods: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'daily', label: 'Daily Report', icon: '▣' },
    { key: 'individual', label: 'Kinerja Individu', icon: '▥' },
    { key: 'ops', label: 'Kinerja OPS', icon: '▤' },
    { key: 'monthly', label: 'Monthly', icon: '▦' },
    { key: 'final', label: 'Nilai Akhir', icon: '★' },
];

const href = (key) => key === 'daily'
    ? route('dashboard.kpi.daily', { period_id: props.periodId, karyawan_id: props.employeeId })
    : route(`dashboard.kpi.${key}`, { period: props.periodId, karyawan_id: props.employeeId });

const currentIndex = computed(() => props.periods.findIndex((period) => Number(period.id) === Number(props.periodId)));
const previousPeriod = computed(() => currentIndex.value > 0 ? props.periods[currentIndex.value - 1] : null);
const nextPeriod = computed(() => currentIndex.value >= 0 && currentIndex.value < props.periods.length - 1 ? props.periods[currentIndex.value + 1] : null);
const selectedPeriod = computed(() => props.periods[currentIndex.value] || null);
const visitPeriod = (periodId) => {
    if (!periodId) return;
    const url = props.active === 'daily'
        ? route('dashboard.kpi.daily', { period_id: periodId, karyawan_id: props.employeeId })
        : route(`dashboard.kpi.${props.active}`, { period: periodId, karyawan_id: props.employeeId });
    router.get(url);
};
</script>

<template>
    <div class="space-y-3">
        <div v-if="periods.length" class="flex flex-col gap-3 rounded-xl border border-[#dce5f1] bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#6079a4]">Context KPI Karyawan</p>
                <p class="mt-0.5 text-xs font-semibold" :class="selectedPeriod?.is_active ? 'text-emerald-700' : 'text-slate-500'">{{ selectedPeriod?.is_active ? 'Periode Aktif' : 'Periode Historis' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" aria-label="Periode sebelumnya" :disabled="!previousPeriod" class="grid h-9 w-9 place-items-center rounded-lg border border-[#cbd8ea] text-lg text-[#173467] disabled:cursor-not-allowed disabled:opacity-35" @click="visitPeriod(previousPeriod?.id)">‹</button>
                <select :value="periodId" class="h-9 min-w-[180px] rounded-lg border-[#cbd8ea] bg-white text-sm font-semibold text-[#173467]" aria-label="Pilih periode KPI" @change="visitPeriod(Number($event.target.value))">
                    <option v-for="period in [...periods].reverse()" :key="period.id" :value="period.id">{{ period.label }}</option>
                </select>
                <button type="button" aria-label="Periode berikutnya" :disabled="!nextPeriod" class="grid h-9 w-9 place-items-center rounded-lg border border-[#cbd8ea] text-lg text-[#173467] disabled:cursor-not-allowed disabled:opacity-35" @click="visitPeriod(nextPeriod?.id)">›</button>
            </div>
        </div>
        <nav class="grid overflow-hidden rounded-lg bg-[#eef4fb] sm:grid-cols-5">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="href(tab.key)"
            :class="tab.key === active ? 'bg-[#1263e8] text-white shadow-sm' : 'text-[#536b95] hover:bg-blue-50'"
            class="flex min-h-[50px] items-center justify-center gap-2 border-white px-3 text-sm font-semibold sm:border-r"
        >
            <span class="text-base">{{ tab.icon }}</span>{{ tab.label }}
        </Link>
        </nav>
    </div>
</template>
