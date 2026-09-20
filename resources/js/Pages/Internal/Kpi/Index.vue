<script setup>
import { computed } from "vue";
import { Link, router } from "@inertiajs/vue3";
import InternalDashboardLayout from "@/Layouts/InternalDashboardLayout.vue";

const props = defineProps({
    user: Object,
    periods: { type: Array, default: () => [] },
    selectedPeriod: { type: Object, default: null },
    activePeriodId: { type: [Number, String], default: null },
});

const months = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];
const periodLabel = (period) => period ? `${months[period.bulan - 1]} ${period.tahun}` : "-";
const selectedLabel = computed(() => periodLabel(props.selectedPeriod));
const isSelectedActive = computed(() => Number(props.selectedPeriod?.id) === Number(props.activePeriodId));
const history = computed(() => props.periods.filter((period) => Number(period.id) !== Number(props.selectedPeriod?.id)));

const statusClass = (key) => ({
    draft: "bg-slate-100 text-slate-600",
    active: "bg-blue-50 text-blue-700",
    waiting_mpa: "bg-amber-50 text-amber-700",
    waiting_hrd: "bg-orange-50 text-orange-700",
    ready_publish: "bg-indigo-50 text-indigo-700",
    published: "bg-emerald-50 text-emerald-700",
    completed: "bg-emerald-100 text-emerald-700",
}[key] || "bg-slate-100 text-slate-600");

const cellStatusClass = (label) => {
    if (["Selesai", "Dipublish", "Siap Dipublish"].includes(label)) return "bg-emerald-50 text-emerald-700";
    if (["Draft", "Draft Penilai", "Menunggu TTD", "Menunggu Finalisasi", "Menunggu HRD"].includes(label)) return "bg-amber-50 text-amber-700";
    if (["Tidak Mengisi", "Parameter Belum Ditetapkan"].includes(label)) return "bg-rose-50 text-rose-700";
    return "bg-slate-100 text-slate-600";
};

const changePeriod = (event) => {
    const periodId = event.target.value;
    router.get(route("dashboard.kpi.index"), periodId ? { period_id: periodId } : {}, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
};

const progressItems = computed(() => {
    const progress = props.selectedPeriod?.progress || {};
    return [
        { key: "participants", label: "Pembentukan Peserta", value: progress.participants },
        { key: "individual", label: "Kinerja Individu", value: progress.individual },
        { key: "ops", label: "Kinerja OPS", value: progress.ops },
        { key: "mpa", label: "MPA", value: progress.mpa },
        { key: "monthly", label: "Monthly", value: progress.monthly },
    ];
});
</script>

<template>
    <InternalDashboardLayout title="Monitoring Periode KPI" :user="user" content-width="wide">
        <div class="min-h-[calc(100vh-64px)] w-full bg-[#f5f8fd] px-4 py-5 sm:px-6 lg:px-8">
            <div v-if="!periods.length" class="rounded-2xl border border-[#dce5f1] bg-white p-10 text-center shadow-sm">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#eaf2ff] text-2xl text-[#1463e8]">◷</div>
                <h1 class="mt-4 text-xl font-bold text-[#0b3475]">Belum ada periode KPI</h1>
                <p class="mt-2 text-sm text-slate-500">Periode akan dibentuk otomatis setelah pergantian bulan.</p>
            </div>
            <div v-else class="space-y-5">
                <header class="flex flex-col gap-4 rounded-2xl border border-[#dce5f1] bg-white p-6 shadow-sm lg:flex-row lg:items-start lg:justify-between">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#5273a8]">KPI / Monitoring Siklus</p><h1 class="mt-1 text-3xl font-bold text-[#0b3475]">Monitoring Periode KPI</h1><p class="mt-2 max-w-2xl text-sm text-[#55709f]">Pantau pembentukan periode, progres penilaian, kesiapan Monthly, dan kendala konfigurasi dalam satu dashboard.</p></div>
                    <label class="w-full text-xs font-semibold text-[#5273a8] lg:w-64">Periode yang dipantau<select class="mt-2 h-11 w-full rounded-lg border-[#cbd8ea] bg-white text-sm font-semibold text-[#173467]" :value="selectedPeriod?.id || ''" @change="changePeriod"><option v-for="period in periods" :key="period.id" :value="period.id">{{ periodLabel(period) }}</option></select></label>
                </header>
                <template v-if="selectedPeriod">
                    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-[#dce5f1] bg-white p-4 shadow-sm"><div class="text-xs font-semibold text-slate-500">{{ isSelectedActive ? "Periode Aktif" : "Periode Dipantau" }}</div><div class="mt-2 flex items-center justify-between gap-2"><strong class="text-lg text-[#0b3475]">{{ selectedLabel }}</strong><span class="rounded-full px-2.5 py-1 text-[11px] font-bold" :class="statusClass(selectedPeriod.status_key)">{{ selectedPeriod.status_label }}</span></div></div>
                        <div class="rounded-xl border border-[#dce5f1] bg-white p-4 shadow-sm"><div class="text-xs font-semibold text-slate-500">Peserta KPI</div><strong class="mt-2 block text-2xl text-[#0b3475]">{{ selectedPeriod.progress.participants.total }}</strong><span class="text-xs text-slate-500">Karyawan dalam snapshot periode</span></div>
                        <div class="rounded-xl border border-[#dce5f1] bg-white p-4 shadow-sm"><div class="text-xs font-semibold text-slate-500">Progress MPA</div><strong class="mt-2 block text-2xl text-[#0b3475]">{{ selectedPeriod.progress.mpa.complete }} / {{ selectedPeriod.progress.mpa.total }}</strong><span class="text-xs text-slate-500">{{ selectedPeriod.progress.mpa.percentage }}% selesai</span></div>
                        <div class="rounded-xl border border-[#dce5f1] bg-white p-4 shadow-sm"><div class="text-xs font-semibold text-slate-500">Monthly Siap</div><strong class="mt-2 block text-2xl text-[#0b3475]">{{ selectedPeriod.progress.monthly.complete }} / {{ selectedPeriod.progress.monthly.total }}</strong><span class="text-xs text-slate-500">{{ selectedPeriod.progress.monthly.percentage }}% siap dipublish</span></div>
                    </section>
                    <section class="rounded-2xl border border-[#dce5f1] bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-2 border-b border-[#e5ebf3] pb-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#5273a8]">Status Periode</p><h2 class="mt-1 text-xl font-bold text-[#0b3475]">{{ selectedLabel }}</h2></div><span class="w-fit rounded-full px-3 py-1.5 text-xs font-bold" :class="statusClass(selectedPeriod.status_key)">{{ selectedPeriod.status_label }}</span></div>
                        <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-5"><div v-for="item in progressItems" :key="item.key" class="rounded-xl border border-[#e1e8f2] bg-[#fbfdff] p-3"><div class="flex items-start justify-between gap-2"><span class="text-xs font-semibold text-[#5273a8]">{{ item.label }}</span><strong class="text-sm text-[#0b3475]">{{ item.value.complete }}/{{ item.value.total }}</strong></div><div class="mt-3 h-2 overflow-hidden rounded-full bg-[#e7edf6]"><div class="h-full rounded-full bg-[#2867e8] transition-all" :style="{ width: `${item.value.percentage}%` }"></div></div><div class="mt-2 text-right text-[11px] font-semibold text-slate-500">{{ item.value.percentage }}%</div></div></div>
                        <div class="mt-5 grid grid-cols-1 gap-3 border-t border-[#e5ebf3] pt-5 md:grid-cols-4"><div class="rounded-lg bg-[#f7faff] p-3"><span class="block text-xs text-slate-500">Penilai MPA</span><strong class="mt-1 block text-sm text-[#173467]">{{ selectedPeriod.evaluator_name }}</strong></div><div class="rounded-lg bg-[#f7faff] p-3"><span class="block text-xs text-slate-500">Configuration Error</span><strong class="mt-1 block text-sm" :class="selectedPeriod.configuration_errors ? 'text-rose-600' : 'text-emerald-700'">{{ selectedPeriod.configuration_errors ? `${selectedPeriod.configuration_errors} Masalah` : 'Tidak ada masalah' }}</strong></div><div class="rounded-lg bg-[#f7faff] p-3"><span class="block text-xs text-slate-500">Publish Monthly</span><strong class="mt-1 block text-sm text-[#173467]">{{ selectedPeriod.publish.label }}</strong><span v-if="selectedPeriod.publish.published_at" class="mt-1 block text-[11px] text-slate-500">{{ selectedPeriod.publish.published_at }}</span></div><div class="rounded-lg bg-[#f7faff] p-3"><span class="block text-xs text-slate-500">Signature</span><strong class="mt-1 block text-sm text-[#173467]">{{ selectedPeriod.progress.signature.complete }} / {{ selectedPeriod.progress.signature.total }} peserta selesai</strong></div></div>
                        <div v-if="selectedPeriod.configuration_issues.length" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4"><div class="flex items-start gap-3"><span class="text-lg">⚠</span><div><strong class="text-sm text-amber-800">Masalah konfigurasi perlu ditindaklanjuti</strong><ul class="mt-1 space-y-1 text-xs text-amber-800"><li v-for="issue in selectedPeriod.configuration_issues" :key="issue.type">{{ issue.message }}</li></ul></div></div></div>
                    </section>
                    <section class="rounded-2xl border border-[#dce5f1] bg-white p-5 shadow-sm sm:p-6"><div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#5273a8]">Detail Periode KPI</p><h2 class="mt-1 text-xl font-bold text-[#0b3475]">Monitoring per karyawan — {{ selectedLabel }}</h2></div><span class="text-xs text-slate-500">{{ selectedPeriod.progress.signature.complete }} peserta telah menyelesaikan seluruh signature</span></div><div class="mt-5 overflow-x-auto"><table class="min-w-[980px] w-full text-left text-xs"><thead><tr class="border-b border-[#dce5f1] text-[11px] uppercase tracking-wide text-[#5273a8]"><th class="px-3 py-3">Nama</th><th class="px-3 py-3">Jabatan</th><th class="px-3 py-3">Kinerja Individu</th><th class="px-3 py-3">Kinerja OPS</th><th class="px-3 py-3">MPA</th><th class="px-3 py-3">Monthly</th><th class="px-3 py-3">Tanda Tangan</th><th class="px-3 py-3">Masalah</th></tr></thead><tbody class="divide-y divide-[#edf1f7]"><tr v-for="participant in selectedPeriod.participants" :key="participant.id" class="hover:bg-[#fbfdff]"><td class="px-3 py-3 font-semibold text-[#173467]">{{ participant.nama }}</td><td class="px-3 py-3 text-slate-600">{{ participant.jabatan }}</td><td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 font-semibold" :class="cellStatusClass(participant.individual_status)">{{ participant.individual_status }}</span></td><td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 font-semibold" :class="cellStatusClass(participant.ops_status)">{{ participant.ops_status }}</span></td><td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 font-semibold" :class="cellStatusClass(participant.mpa_status)">{{ participant.mpa_status }}</span></td><td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 font-semibold" :class="cellStatusClass(participant.monthly_status)">{{ participant.monthly_status }}</span></td><td class="px-3 py-3 font-semibold text-[#173467]">{{ participant.signature_status }}</td><td class="px-3 py-3"><span v-if="participant.problems.length" class="text-rose-600">{{ participant.problems.join(', ') }}</span><span v-else class="text-slate-400">-</span></td></tr><tr v-if="!selectedPeriod.participants.length"><td colspan="8" class="px-3 py-10 text-center text-slate-500">Belum tersedia data participant pada periode ini.</td></tr></tbody></table></div></section>
                </template>
                <section class="rounded-2xl border border-[#dce5f1] bg-white p-5 shadow-sm sm:p-6"><div class="flex items-center justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#5273a8]">Riwayat Periode KPI</p><h2 class="mt-1 text-xl font-bold text-[#0b3475]">Siklus sebelumnya</h2></div><span class="text-xs text-slate-500">{{ history.length }} periode</span></div><div v-if="history.length" class="mt-4 space-y-3"><div v-for="period in history" :key="period.id" class="rounded-xl border border-[#e1e8f2] p-4"><div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"><div class="min-w-[170px]"><strong class="text-sm text-[#173467]">{{ periodLabel(period) }}</strong><span class="mt-1 block w-fit rounded-full px-2.5 py-1 text-[11px] font-bold" :class="statusClass(period.status_key)">{{ period.status_label }}</span></div><div class="grid flex-1 grid-cols-2 gap-3 sm:grid-cols-5"><div v-for="item in [{key:'individual',label:'Individu'},{key:'ops',label:'OPS'},{key:'mpa',label:'MPA'},{key:'monthly',label:'Monthly'},{key:'signature',label:'Signature'}]" :key="item.key"><span class="block text-[11px] text-slate-500">{{ item.label }}</span><strong class="text-sm text-[#173467]">{{ period.progress[item.key].complete }}/{{ period.progress[item.key].total }}</strong><div class="mt-1 h-1.5 rounded-full bg-[#e7edf6]"><div class="h-full rounded-full bg-[#2867e8]" :style="{width:`${period.progress[item.key].percentage}%`}"></div></div></div></div><Link :href="route('dashboard.kpi.index', { period_id: period.id })" class="shrink-0 rounded-lg border border-[#b8ccee] px-3 py-2 text-xs font-bold text-[#1463e8] hover:bg-[#edf4ff]">Detail →</Link></div></div></div><div v-else class="mt-4 rounded-xl border border-dashed border-[#cbd8ea] p-7 text-center text-sm text-slate-500">Belum ada periode sebelumnya.</div></section>
            </div>
        </div>
    </InternalDashboardLayout>
</template>
