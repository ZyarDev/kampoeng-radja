<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    periodId: { type: Number, required: true },
    employeeId: { type: Number, required: true },
    active: { type: String, required: true },
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
</script>

<template>
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
</template>
