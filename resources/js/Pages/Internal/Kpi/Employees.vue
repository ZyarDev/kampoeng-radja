<script setup>
import { computed, ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import InternalDashboardLayout from "@/Layouts/InternalDashboardLayout.vue";
import { useConfirmation } from "@/composables/useConfirmation";
const props = defineProps({
    user: Object,
    period: Object,
    currentPeriodId: Number,
    participants: Array,
    hierarchy: Array,
});
const search = ref("");
const expanded = ref({});
const confirmation = useConfirmation();
const monthNames = [
    "Januari",
    "Februari",
    "Maret",
    "April",
    "Mei",
    "Juni",
    "Juli",
    "Agustus",
    "September",
    "Oktober",
    "November",
    "Desember",
];
const periodLabel = computed(
    () =>
        `${monthNames[(props.period?.bulan || 1) - 1]} ${props.period?.tahun || ""}`,
);
const filterMembers = (members) =>
    (members || []).filter((p) => {
        const q = search.value.trim().toLowerCase();
        return (
            !q ||
            [p.nama, p.nip, p.jabatan, p.departemen, p.penempatan].some((v) =>
                String(v || "")
                    .toLowerCase()
                    .includes(q),
            )
        );
    });
const visibleHierarchy = computed(() => {
    const groups = (props.hierarchy || []).map((group, index) => ({
        ...group,
        originalIndex: index,
        filteredParticipants: filterMembers(group.participants),
    }));
    if (!search.value.trim()) return groups;
    return groups
        .filter((group) => group.filteredParticipants.length)
        .sort((a, b) => a.originalIndex - b.originalIndex);
});
const isExpanded = (level) => expanded.value[level] !== false;
const toggle = (level) => {
    expanded.value[level] = !isExpanded(level);
};
const progressDotClass = (status) =>
    ({
        waiting_approval: "bg-amber-500",
        waiting_hrd: "bg-amber-500",
        waiting_signature: "bg-amber-500",
        approved: "bg-emerald-500",
        approved_takeover: "bg-emerald-800",
        ready: "bg-blue-500",
        draft: "bg-blue-500",
        needs_input: "bg-blue-500",
        not_filled: "bg-rose-500",
        configuration_error: "bg-rose-500",
        none: "bg-slate-400",
    })[status] || "bg-slate-400";
const progressBoxClass = (status) =>
    ({
        waiting_approval: "border-amber-200 bg-amber-50/80",
        waiting_hrd: "border-amber-200 bg-amber-50/80",
        waiting_signature: "border-amber-200 bg-amber-50/80",
        approved: "border-emerald-200 bg-emerald-50/80",
        approved_takeover: "border-emerald-700 bg-emerald-800 text-white",
        ready: "border-blue-200 bg-blue-50/80",
        draft: "border-blue-200 bg-blue-50/80",
        needs_input: "border-blue-200 bg-blue-50/80",
        not_filled: "border-rose-200 bg-rose-50/80",
        configuration_error: "border-rose-200 bg-rose-50/80",
        none: "border-slate-200 bg-slate-50/80",
    })[status] || "border-slate-200 bg-slate-50/80";

// Keep the Daily summary actionable even when an older response payload only
// contains the pending report ids. The backend remains the source of truth;
// this fallback only prevents a stale/partial payload from showing "Belum
// Diisi" while an approval is visibly pending for the row.
const progressFor = (participant, key) => {
    const existingProgress = participant.progress?.[key];

    if (
        key === "daily" &&
        ((participant.pending_daily_count || 0) > 0 ||
            (participant.pending_daily_ids || []).length > 0 ||
            participant.daily_status === "waiting_approval")
    ) {
        return existingProgress || {
            status: "waiting_approval",
            label: "Menunggu Persetujuan",
            detail: "Ada laporan Daily yang menunggu persetujuan",
        };
    }

    return participant.progress?.[key] || {
        status: "none",
        label: "Belum Tersedia",
    };
};
const compactProgressLabel = (participant, key) => {
    const progress = progressFor(participant, key);

    if (key === "daily") {
        const parts = [];
        const pendingCount = Number(
            progress.pending_count ?? participant.pending_daily_count ?? 0,
        );
        const needsInputCount = Number(
            progress.needs_input_count ?? participant.missing_daily_count ?? 0,
        );
        const notFilledCount = Number(
            progress.not_filled_count ?? participant.not_filled_daily_count ?? 0,
        );
        const takeoverCount = Number(
            progress.takeover_count ?? participant.takeover_daily_count ?? 0,
        );

        if (pendingCount > 0) parts.push(`Approval ${pendingCount}`);
        else if (needsInputCount > 0) parts.push(`Perlu Isi ${needsInputCount}`);
        else if (notFilledCount > 0) parts.push(`Tidak Isi ${notFilledCount}`);
        else if (["approved", "approved_takeover"].includes(progress.status))
            parts.push("Selesai");

        if (pendingCount === 0 && needsInputCount > 0 && notFilledCount > 0)
            parts.push(`Tidak Isi ${notFilledCount}`);
        if (takeoverCount > 0) parts.push(`Dialihkan ${takeoverCount}`);

        return parts.length ? parts.join(" · ") : "Belum Diisi";
    }

    return (
        {
            "Menunggu Persetujuan": "Approval",
            "Menunggu Tanda Tangan": "Menunggu TTD",
            "Parameter Belum Ditetapkan": "Parameter Kosong",
            "Siap Dipublish": "Siap Publish",
        }[progress.label] || progress.label
    );
};
const compactProgressParts = (participant, key) =>
    compactProgressLabel(participant, key)
        .split(" · ")
        .map((text) => ({ text, takeover: text.startsWith("Dialihkan ") }));
const dailyStats = (participant) => {
    const progress = progressFor(participant, "daily");
    return [
        { count: Number(progress.approved_count ?? 0), label: "Disetujui", tone: "approved" },
        { count: Number(progress.pending_count ?? 0), label: "Menunggu Approv", tone: "pending" },
        { count: Number(progress.needs_input_count ?? 0), label: "Perlu Diisi", tone: "needs" },
        { count: Number(progress.takeover_count ?? 0), label: "Dialihkan", tone: "takeover" },
        { count: Number(progress.not_filled_count ?? 0), label: "Tidak Diisi", tone: "not-filled" },
    ];
};
const dailyStatClass = (tone) => ({
    approved: "text-emerald-700",
    pending: "text-amber-700",
    needs: "text-blue-700",
    takeover: "text-emerald-800",
    "not-filled": "text-rose-700",
}[tone] || "text-slate-600");
const dailyMainStatusLabel = (participant) => ({
    waiting_approval: "Menunggu Approval",
    needs_input: "Perlu Diisi",
    not_filled: "Tidak Diisi",
    approved: "Selesai",
    approved_takeover: "Selesai",
    none: "Belum Diisi",
}[progressFor(participant, "daily").status] || "Belum Diisi");
const bulkApprove = async (group) => {
    const ids = filterMembers(group.participants).flatMap(
        (p) => p.pending_daily_ids || [],
    );
    if (!ids.length) return;
    if (
        await confirmation.confirm({
            title: "Tanda Tangani Semua Daily?",
            message: `${ids.length} Daily Report bawahan langsung akan disetujui dan dikunci.`,
            confirmText: "Tanda Tangani",
        })
    ) {
        router.post(
            route("dashboard.kpi.daily.bulk-approve"),
            { report_ids: ids },
            { preserveScroll: true },
        );
    }
};
</script>
<template>
    <InternalDashboardLayout
        title="KPI-Karyawan"
        :user="user"
        content-width="wide"
        ><div
            class="min-h-[calc(100vh-64px)] bg-[#f5f8fd] px-4 py-5 sm:px-6 lg:px-7"
        >
            <div class="w-full space-y-5">
                <header
                    class="flex flex-col gap-4 rounded-2xl border border-[#dce5f1] bg-white p-6 shadow-sm sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <div class="text-xs font-semibold text-[#5273a8]">
                            KPI Individu / {{ periodLabel }}
                        </div>
                        <h1 class="mt-1 text-[28px] font-bold text-[#0b3475]">
                            KPI-Karyawan
                        </h1>
                        <p class="mt-1 text-sm text-[#55709f]">
                            Pantau dan kelola progres KPI bawahan Anda
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
                        <span
                            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-700"
                            >● Menunggu approval</span
                        ><span
                            class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-700"
                            >● Disetujui</span
                        ><span
                            class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-rose-700"
                            >● Tidak Mengisi</span
                        >
                    </div>
                </header>
                <div
                    class="flex flex-col gap-3 rounded-xl border border-[#dce5f1] bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-slate-500">
                        Periode performa:
                        <strong class="text-[#173467]">{{
                            periodLabel
                        }}</strong>
                    </p>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama, NIK, atau jabatan..."
                        class="h-10 w-full rounded-lg border-[#d5deea] text-sm sm:w-80"
                    />
                </div>
                <section
                    v-for="group in visibleHierarchy"
                    :key="group.level"
                    class="overflow-hidden rounded-2xl border border-[#dce5f1] bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-[#e4eaf2]"
                    >
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-between px-5 py-4 text-left"
                            @click="toggle(group.level)"
                        >
                            <span class="flex items-center gap-3"
                                ><span
                                    class="grid h-9 w-9 place-items-center rounded-lg bg-[#eaf2ff] text-[#1463e8]"
                                    >♙</span
                                ><span
                                    ><strong
                                        class="block text-base text-[#102f66]"
                                        >{{ group.label }}</strong
                                    ><small class="text-xs text-slate-500"
                                        >{{
                                            group.filteredParticipants.length
                                        }}
                                        karyawan</small
                                    ></span
                                ></span
                            ><span class="text-slate-400">{{
                                isExpanded(group.level) ? "⌃" : "⌄"
                            }}</span></button
                        ><button
                            v-if="
                                group.level === 1 &&
                                group.filteredParticipants.some(
                                    (p) => (p.pending_daily_ids || []).length,
                                )
                            "
                            type="button"
                            class="mr-5 rounded-lg bg-[#1463e8] px-3 py-2 text-[11px] font-bold text-white"
                            @click="bulkApprove(group)"
                        >
                            Tanda Tangani Semua Daily
                        </button>
                    </div>
                    <div v-if="isExpanded(group.level)" class="overflow-x-auto">
                        <table class="w-full min-w-[1200px] table-fixed text-sm">
                            <colgroup>
                                <col class="w-14" />
                                <col />
                                <col />
                                <col />
                                <col />
      <col class="w-[460px]" />
                                <col class="w-40" />
                            </colgroup>
                            <thead
                                class="bg-[#f7f9fc] text-left text-[11px] font-bold uppercase tracking-wide text-[#60749a]"
                            >
                                <tr>
                                    <th class="px-5 py-3 text-center">
                                        No
                                    </th>
                                    <th class="px-4 py-3">Nama / NIK</th>
                                    <th class="px-4 py-3">Jabatan</th>
                                    <th class="px-4 py-3">Departemen</th>
                                    <th class="px-4 py-3">Penempatan</th>
                                    <th class="px-4 py-3">Status KPI</th>
                                    <th class="px-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e4eaf2]">
                                <tr
                                    v-for="(
                                        p, index
                                    ) in group.filteredParticipants"
                                    :key="p.id"
                                    class="hover:bg-[#f8fbff]"
                                >
                                    <td
                                        class="px-5 py-4 text-center text-slate-400"
                                    >
                                        {{ index + 1 }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <strong
                                            class="block text-sm text-[#173467]"
                                            >{{ p.nama }}</strong
                                        ><span class="text-xs text-slate-400">{{
                                            p.nik || "-"
                                        }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-slate-600">
                                        {{ p.jabatan || "-" }}
                                    </td>
                                    <td class="px-4 py-4 text-slate-600">
                                        {{ p.departemen || "-" }}
                                    </td>
                                    <td class="px-4 py-4 text-slate-600">
                                        {{ p.penempatan || "-" }}
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="grid w-full grid-cols-3 gap-1">
                                            <div
                                                v-for="item in [
                                                    { key: 'daily', label: 'Daily Report' },
                                                    { key: 'individu', label: 'KI' },
                                                    { key: 'ops', label: 'OPS' },
                                                    { key: 'mpa_monthly', label: 'MPA' },
                                                ]"
                                                :key="item.key"
                                                :class="[
                                                    progressBoxClass(progressFor(p, item.key).status),
                                                    item.key === 'daily'
                                                        ? 'col-span-3 block'
                                                        : 'flex items-center gap-3',
                                                ]"
                                                class="min-w-0 rounded-md border px-2 py-1.5"
                                            >
                                                <template v-if="item.key === 'daily'">
                                                    <div class="flex min-w-0 items-center gap-2">
                                                        <span class="shrink-0 text-[10px] font-extrabold uppercase tracking-wide text-[#456084]">Daily Report</span>
                                                        <span :class="progressDotClass(progressFor(p, item.key).status)" class="h-2 w-2 shrink-0 rounded-full" aria-hidden="true"></span>
                                                        <span class="truncate text-[10px] font-bold text-slate-700">{{ dailyMainStatusLabel(p) }}</span>
                                                    </div>
                                                    <div class="mt-1.5 grid grid-cols-5 gap-1 border-t border-current/10 pt-1.5">
                                                        <div v-for="stat in dailyStats(p)" :key="stat.label" class="min-w-0 text-center" :class="dailyStatClass(stat.tone)">
                                                            <div class="flex flex-col items-center gap-1 text-[11px] font-extrabold leading-none"><span>{{ stat.count }}</span><span class="text-[8px] font-bold uppercase leading-tight tracking-tight">{{ stat.label }}</span></div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template v-else>
                                                    <span :class="progressFor(p, item.key).status === 'approved_takeover' ? 'text-white' : 'text-[#456084]'" class="shrink-0 text-[10px] font-extrabold uppercase tracking-wide">{{ item.label }}</span>
                                                    <span class="flex min-w-0 items-center gap-1.5 text-[10px] font-semibold" :title="progressFor(p, item.key).detail || progressFor(p, item.key).label">
                                                        <span :class="progressDotClass(progressFor(p, item.key).status)" class="h-2 w-2 shrink-0 rounded-full" aria-hidden="true"></span>
                                                        <span class="truncate">{{ compactProgressLabel(p, item.key) }}</span>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <Link
                                            :href="
                                                route('dashboard.kpi.daily', {
                                                    karyawan_id: p.karyawan_id,
                                                    period_id: props.currentPeriodId || period.id,
                                                })
                                            "
                                            class="inline-flex whitespace-nowrap rounded-lg bg-[#1463e8] px-3 py-2 text-[11px] font-bold text-white transition hover:bg-[#0f55c4]"
                                        >
                                            KPI Karyawan
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div></InternalDashboardLayout
    >
</template>
