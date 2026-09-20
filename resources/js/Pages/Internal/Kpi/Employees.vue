<script setup>
import { computed, ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import InternalDashboardLayout from "@/Layouts/InternalDashboardLayout.vue";
import { useConfirmation } from "@/composables/useConfirmation";
const props = defineProps({
    user: Object,
    period: Object,
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
const dailyClass = (status) =>
    ({
        waiting_approval: "bg-amber-100 text-amber-700 border-amber-200",
        approved: "bg-emerald-100 text-emerald-700 border-emerald-200",
        not_filled: "bg-rose-100 text-rose-700 border-rose-200",
        none: "bg-slate-100 text-slate-500 border-slate-200",
    })[status] || "bg-slate-100 text-slate-500 border-slate-200";
const dailyLabel = (status) =>
    ({
        waiting_approval: "Menunggu",
        approved: "Disetujui",
        not_filled: "Tidak Mengisi",
        none: "Belum Ada",
    })[status] || "Belum Ada";
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
                        <table class="min-w-[1040px] w-full text-sm">
                            <thead
                                class="bg-[#f7f9fc] text-left text-[11px] font-bold uppercase tracking-wide text-[#60749a]"
                            >
                                <tr>
                                    <th class="w-14 px-5 py-3 text-center">
                                        No
                                    </th>
                                    <th class="px-4 py-3">Nama / NIK</th>
                                    <th class="px-4 py-3">Jabatan</th>
                                    <th class="px-4 py-3">Departemen</th>
                                    <th class="px-4 py-3">Penempatan</th>
                                    <th class="px-4 py-3">Status DR</th>
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
                                    <td class="px-4 py-4">
                                        <span
                                            :class="dailyClass(p.daily_status)"
                                            class="rounded-md border px-2.5 py-1 text-[11px] font-semibold"
                                            >DR ·
                                            {{
                                                dailyLabel(p.daily_status)
                                            }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-1.5">
                                            <Link
                                                :href="
                                                    route(
                                                        'dashboard.kpi.daily',
                                                        {
                                                            karyawan_id:
                                                                p.karyawan_id,
                                                            period_id:
                                                                period.id,
                                                        },
                                                    )
                                                "
                                                class="rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700"
                                                >DR</Link
                                            ><Link
                                                :href="route('dashboard.kpi.individual', { period: period.id, karyawan_id: p.karyawan_id })"
                                                class="rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700"
                                                title="Buka Kinerja Individu karyawan"
                                                >KI</Link
                                            ><Link
                                                :href="route('dashboard.kpi.ops', { period: period.id, karyawan_id: p.karyawan_id })"
                                                class="rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700"
                                                title="Buka Kinerja OPS karyawan"
                                                >K-OPS</Link
                                            ><Link
                                                :href="route('dashboard.kpi.monthly', { period: period.id, karyawan_id: p.karyawan_id })"
                                                class="rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700"
                                                title="Buka Monthly karyawan"
                                                >M</Link
                                            ><Link
                                                :href="route('dashboard.kpi.final', { period: period.id, karyawan_id: p.karyawan_id })"
                                                class="rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700"
                                                title="Buka Nilai Akhir karyawan"
                                                >NA</Link
                                            >
                                        </div>
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
